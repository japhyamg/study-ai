<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Academic structure.
 *
 * Replaces the flat `classes` table with the shape a real school actually has:
 *
 *   AcademicSession (2024/2025)
 *     └── Term (sequence 1..n, one current)
 *
 *   ClassLevel  ("Year 10")   — curriculum band, ordered, promotable
 *     └── ClassArm ("Year 10 B") — the actual group of students,
 *                                  capacity, stream, form teacher
 *
 *   Subject × ClassArm → one teacher   (ClassSubjectAssignment, unique)
 *
 *   AssessmentType (CA1 20% / CA2 20% / Exam 60%) — weighted components
 *                                                   that sum to a term score
 *
 * The old `classes` table conflated level and arm, allowed only one teacher
 * for a whole class regardless of subject, and had no concept of assessment
 * components. Existing rows are carried over: every class becomes an arm, and
 * a level is derived from its name.
 *
 * Everything here is deliberately generic — no country-specific values are
 * baked into the schema. Nigerian defaults ship in the seeder / bootstrapper.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Schema first — every table exists before any data moves.
        $this->createAcademicSessions();
        $this->createClassLevels();
        $this->createClassArms();
        $this->createAssessmentTypes();
        $this->createClassSubjectAssignments();
        $this->extendSubjects();

        // 2. Then migrate existing data into the new shape.
        $this->restructureTerms();
        $this->migrateClassesToArms();
        $this->addCurrentPointersToSchools();
        $this->repointClassForeignKeys();
    }

    // ───────────────────────── sessions ─────────────────────────

    private function createAcademicSessions(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name');                 // "2024/2025"
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_current']);
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });
    }

    /**
     * Terms become children of a session and gain an explicit ordering.
     * The old boolean `active` becomes `is_current`.
     */
    private function restructureTerms(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->uuid('academic_session_id')->nullable()->after('school_id');
            $table->unsignedSmallInteger('sequence')->default(1)->after('name');
            $table->boolean('is_current')->default(false)->after('sequence');
            $table->date('resumption_date')->nullable()->after('end_date');

            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->cascadeOnDelete();
            $table->index(['school_id', 'is_current']);
        });

        // Give every existing school a session and hang its terms off it.
        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            $terms = DB::table('terms')->where('school_id', $schoolId)->orderBy('created_at')->get();

            if ($terms->isEmpty()) {
                continue;
            }

            $sessionId = (string) Str::uuid();

            DB::table('academic_sessions')->insert([
                'id' => $sessionId,
                'school_id' => $schoolId,
                'name' => $this->defaultSessionName(),
                'start_date' => optional($terms->first())->start_date,
                'end_date' => optional($terms->last())->end_date,
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($terms->values() as $i => $term) {
                DB::table('terms')->where('id', $term->id)->update([
                    'academic_session_id' => $sessionId,
                    'sequence' => $i + 1,
                    'is_current' => (bool) ($term->active ?? false),
                ]);
            }
        }

        // Ensure exactly one current term per school.
        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            $current = DB::table('terms')->where('school_id', $schoolId)->where('is_current', true)->count();

            if ($current === 0) {
                $first = DB::table('terms')->where('school_id', $schoolId)->orderBy('sequence')->first();
                if ($first) {
                    DB::table('terms')->where('id', $first->id)->update(['is_current' => true]);
                }
            }
        }

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }

    private function extendSubjects(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('category')->default('core')->after('code');  // core|elective|vocational
            $table->json('applies_to')->nullable()->after('category');   // class level codes
            $table->text('description')->nullable()->after('applies_to');
            $table->boolean('is_active')->default(true)->after('description');

            $table->index(['school_id', 'is_active']);
        });
    }

    // ───────────────────────── levels & arms ─────────────────────────

    private function createClassLevels(): void
    {
        Schema::create('class_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name');                                  // "Year 10"
            $table->string('code');                                  // "y10"
            $table->string('stage')->nullable();                     // free-form band
            $table->unsignedSmallInteger('position')->default(0);    // promotion order
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'position']);
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });
    }

    /** `classes` becomes `class_arms` — see migrateClassesToArms() for the data move. */
    private function createClassArms(): void
    {
        Schema::create('class_arms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('class_level_id');
            $table->uuid('academic_session_id')->nullable();
            $table->uuid('form_teacher_id')->nullable();
            $table->string('name');                                // "A", "Blue"
            $table->string('stream')->nullable();                  // free-form
            $table->unsignedSmallInteger('capacity')->default(40);
            $table->text('description')->nullable();
            $table->string('invite_code')->nullable()->unique();
            $table->timestamps();

            $table->unique(['class_level_id', 'name']);
            $table->index(['school_id', 'class_level_id']);
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('class_level_id')->references('id')->on('class_levels')->cascadeOnDelete();
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->nullOnDelete();
            $table->foreign('form_teacher_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Carry existing classes over: each becomes an arm, and a level is derived
     * from its name so nothing is orphaned. Ids are preserved so every foreign
     * key that pointed at `classes` stays valid.
     */
    private function migrateClassesToArms(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        // One level per distinct class name, then the class itself as arm "A".
        foreach (DB::table('classes')->orderBy('created_at')->get() as $i => $class) {
            $levelName = trim((string) $class->name) ?: 'General';
            $levelCode = Str::slug($levelName) ?: 'level-'.($i + 1);

            $level = DB::table('class_levels')
                ->where('school_id', $class->school_id)
                ->where('code', $levelCode)
                ->first();

            if (! $level) {
                $levelId = (string) Str::uuid();
                DB::table('class_levels')->insert([
                    'id' => $levelId,
                    'school_id' => $class->school_id,
                    'name' => $levelName,
                    'code' => $levelCode,
                    'position' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $levelId = $level->id;
            }

            $sessionId = DB::table('academic_sessions')
                ->where('school_id', $class->school_id)
                ->value('id');

            DB::table('class_arms')->insert([
                'id' => $class->id,                 // keep the id: FKs stay valid
                'school_id' => $class->school_id,
                'class_level_id' => $levelId,
                'academic_session_id' => $sessionId,
                'form_teacher_id' => $class->teacher_id ?? null,
                'name' => 'A',
                'capacity' => 40,
                'description' => $class->description ?? null,
                'invite_code' => $class->invite_code ?? null,
                'created_at' => $class->created_at ?? now(),
                'updated_at' => $class->updated_at ?? now(),
            ]);

            // The old single teacher_id becomes a proper per-subject assignment.
            if (! empty($class->teacher_id) && ! empty($class->subject_id)) {
                DB::table('class_subject_assignments')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'school_id' => $class->school_id,
                    'class_arm_id' => $class->id,
                    'subject_id' => $class->subject_id,
                    'teacher_id' => $class->teacher_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    // ───────────────────────── assessment & assignments ─────────────────────────

    private function createAssessmentTypes(): void
    {
        Schema::create('assessment_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name');                                 // "Continuous Assessment 1"
            $table->string('code');                                 // "ca1"
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->decimal('weight_percent', 5, 2)->default(0);    // contribution to the term score
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'position']);
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });
    }

    /**
     * One teacher owns one subject in one arm. Enforced by the database, not
     * by application code, so it cannot drift.
     */
    private function createClassSubjectAssignments(): void
    {
        Schema::create('class_subject_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('class_arm_id');
            $table->uuid('subject_id');
            $table->uuid('teacher_id');
            $table->timestamps();

            $table->unique(['class_arm_id', 'subject_id'], 'csa_arm_subject_unique');
            $table->index(['school_id']);
            $table->index(['teacher_id']);
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
            $table->foreign('class_arm_id')->references('id')->on('class_arms')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function addCurrentPointersToSchools(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->uuid('current_session_id')->nullable()->after('settings');
            $table->uuid('current_term_id')->nullable()->after('current_session_id');

            $table->foreign('current_session_id')->references('id')->on('academic_sessions')->nullOnDelete();
            $table->foreign('current_term_id')->references('id')->on('terms')->nullOnDelete();
        });

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            DB::table('schools')->where('id', $schoolId)->update([
                'current_session_id' => DB::table('academic_sessions')
                    ->where('school_id', $schoolId)->where('is_current', true)->value('id'),
                'current_term_id' => DB::table('terms')
                    ->where('school_id', $schoolId)->where('is_current', true)->value('id'),
            ]);
        }
    }

    /**
     * Point everything that referenced `classes` at `class_arms` instead.
     * Ids were preserved during the copy, so the values carry straight over.
     */
    private function repointClassForeignKeys(): void
    {
        $targets = [
            'class_enrollments' => 'class_id',
            'materials' => 'class_id',
            'exams' => 'class_id',
            'invite_codes' => 'class_id',
        ];

        foreach ($targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            // Drop the old FK first — the parent table is about to disappear.
            Schema::table($table, function (Blueprint $t) use ($column) {
                try {
                    $t->dropForeign([$column]);
                } catch (\Throwable $e) {
                    // Driver may not have named it as expected; safe to continue.
                }
            });

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->renameColumn($column, 'class_arm_id');
            });

            Schema::table($table, function (Blueprint $t) use ($table) {
                $onDelete = $table === 'class_enrollments' ? 'cascade' : 'set null';

                $fk = $t->foreign('class_arm_id')->references('id')->on('class_arms');
                $onDelete === 'cascade' ? $fk->cascadeOnDelete() : $fk->nullOnDelete();
            });
        }

        Schema::dropIfExists('classes');
    }

    private function defaultSessionName(): string
    {
        $year = (int) now()->year;
        $start = now()->month >= 9 ? $year : $year - 1;

        return $start.'/'.($start + 1);
    }

    // ───────────────────────── down ─────────────────────────

    public function down(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('term_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('teacher_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('invite_code')->nullable()->unique();
            $table->timestamps();
        });

        foreach (['class_enrollments', 'materials', 'exams', 'invite_codes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'class_arm_id')) {
                Schema::table($table, function (Blueprint $t) {
                    try {
                        $t->dropForeign(['class_arm_id']);
                    } catch (\Throwable $e) {
                    }
                    $t->renameColumn('class_arm_id', 'class_id');
                });
            }
        }

        Schema::table('schools', function (Blueprint $table) {
            $table->dropForeign(['current_session_id']);
            $table->dropForeign(['current_term_id']);
            $table->dropColumn(['current_session_id', 'current_term_id']);
        });

        Schema::dropIfExists('class_subject_assignments');
        Schema::dropIfExists('assessment_types');
        Schema::dropIfExists('class_arms');
        Schema::dropIfExists('class_levels');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['category', 'applies_to', 'description', 'is_active']);
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->dropForeign(['academic_session_id']);
            $table->boolean('active')->default(false);
            $table->dropColumn(['academic_session_id', 'sequence', 'is_current', 'resumption_date']);
        });

        Schema::dropIfExists('academic_sessions');
    }
};
