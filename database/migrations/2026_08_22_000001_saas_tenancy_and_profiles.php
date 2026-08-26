<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS separation-of-concerns migration.
 *
 *  1. Tenancy — schools gain a `subdomain` (school.example.com) plus
 *     an optional vanity `domain`, lifecycle status and branding.
 *
 *  2. Super-admins live in their OWN table + guard (`superadmin`). They are
 *     platform staff and are never members of a school, so they cannot be
 *     confused with a school user and cannot log in on a school subdomain.
 *
 *  3. School users keep a single `users` row (one credential store, one login
 *     route, one 2FA implementation) and gain exactly one role-profile row per
 *     school: admin_profiles / teacher_profiles / student_profiles. Role-specific
 *     columns live on the profile, never on `users`.
 *
 *  4. Two-factor auth columns use Laravel Fortify's exact column names so the
 *     package can be dropped in later without a data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ───────────────────────── 1. TENANCY ─────────────────────────

        Schema::table('schools', function (Blueprint $table) {
            $table->string('subdomain')->nullable()->unique()->after('slug');
            $table->string('domain')->nullable()->unique()->after('subdomain');
            $table->string('status')->default('active')->after('logo'); // active|suspended|pending
            $table->string('primary_color')->nullable()->after('status');
            $table->string('contact_email')->nullable()->after('primary_color');
            $table->string('phone')->nullable()->after('contact_email');
            $table->string('timezone')->default('UTC')->after('phone');
            $table->text('address')->nullable()->after('timezone');
            $table->json('settings')->nullable()->after('address');
            $table->timestamp('trial_ends_at')->nullable()->after('settings');
            $table->timestamp('suspended_at')->nullable()->after('trial_ends_at');
        });

        // Backfill a subdomain for every existing school from its slug.
        foreach (DB::table('schools')->select('id', 'slug')->get() as $row) {
            DB::table('schools')->where('id', $row->id)->update([
                'subdomain' => $this->uniqueSubdomain($row->slug, $row->id),
            ]);
        }

        // ───────────────────────── 2. SUPER ADMINS (own guard) ─────────────────────────

        Schema::create('super_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);

            // Fortify-compatible 2FA columns
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('super_admin_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ───────────────────────── 3. SCHOOL USER ACCOUNT HARDENING ─────────────────────────

        Schema::table('users', function (Blueprint $table) {
            // Fortify-compatible 2FA columns
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->string('phone')->nullable()->after('image');
            $table->string('locale', 12)->default('en')->after('phone');
            $table->string('timezone')->nullable()->after('locale');
            $table->boolean('is_active')->default(true)->after('timezone');
            $table->timestamp('password_changed_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('password_changed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        // A user's email is unique per-tenant rather than globally, so the same
        // person can hold accounts at two schools. Drop the global unique index.
        $this->dropUsersEmailUnique();

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('school_id')->nullable()->after('id');
            $table->foreign('school_id')->references('id')->on('schools')->nullOnDelete();
            $table->unique(['school_id', 'email'], 'users_school_email_unique');
            $table->index('email');
        });

        // Attach every existing user to the school of their first membership.
        $memberships = DB::table('school_members')
            ->select('user_id', 'school_id')
            ->orderBy('created_at')
            ->get()
            ->unique('user_id');

        foreach ($memberships as $m) {
            DB::table('users')->where('id', $m->user_id)->whereNull('school_id')
                ->update(['school_id' => $m->school_id]);
        }

        // ───────────────────────── 4. ROLE PROFILE TABLES ─────────────────────────

        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->string('staff_number')->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('office_phone')->nullable();
            $table->boolean('is_primary')->default(false); // the school owner
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->index('school_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->string('staff_number')->nullable();
            $table->string('title')->nullable();            // Mr / Mrs / Dr …
            $table->string('department')->nullable();
            $table->string('qualification')->nullable();
            $table->json('specialisations')->nullable();     // subject names / ids
            $table->date('hired_on')->nullable();
            $table->text('bio')->nullable();
            $table->string('office_hours')->nullable();
            $table->string('employment_type')->default('full_time'); // full_time|part_time|contract
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->index('school_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->string('admission_number')->nullable();
            $table->string('grade_level')->nullable();       // JSS1, Year 10 …
            $table->string('section')->nullable();           // stream / arm
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();
            $table->text('address')->nullable();
            $table->date('enrolled_on')->nullable();
            $table->string('status')->default('active');     // active|graduated|withdrawn|suspended
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->unique(['school_id', 'admission_number']);
            $table->index('school_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
        });

        // Backfill a profile row for every existing membership so nothing is orphaned.
        $this->backfillProfiles();

        // ───────────────────────── 5. AUDIT / SESSION SUPPORT ─────────────────────────

        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('school_id')->nullable()->index()->after('user_id');
            $table->string('guard', 32)->nullable()->after('school_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('admin_profiles');
        Schema::dropIfExists('super_admin_password_reset_tokens');
        Schema::dropIfExists('super_admins');

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['school_id', 'guard']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropUnique('users_school_email_unique');
            $table->dropIndex(['email']);
            $table->dropColumn([
                'school_id', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'phone', 'locale', 'timezone',
                'is_active', 'password_changed_at', 'last_login_at', 'last_login_ip',
            ]);
            $table->unique('email');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'subdomain', 'domain', 'status', 'primary_color', 'contact_email',
                'phone', 'timezone', 'address', 'settings', 'trial_ends_at', 'suspended_at',
            ]);
        });
    }

    /** Build a DNS-safe, unique subdomain label from a slug. */
    private function uniqueSubdomain(string $slug, string $id): string
    {
        $base = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug)) ?? '', '-');
        $base = substr($base ?: 'school', 0, 40);

        $candidate = $base;
        $n = 1;
        while (DB::table('schools')->where('subdomain', $candidate)->where('id', '!=', $id)->exists()) {
            $candidate = $base.'-'.(++$n);
        }

        return $candidate;
    }

    /** Portable drop of the global unique index on users.email. */
    private function dropUsersEmailUnique(): void
    {
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        } catch (\Throwable $e) {
            // Index name differs on some drivers — fall back to the column form.
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique(['email']);
                });
            } catch (\Throwable $e2) {
                // Already absent; nothing to do.
            }
        }
    }

    /** Create the matching role profile for every existing school membership. */
    private function backfillProfiles(): void
    {
        $rows = DB::table('school_members')->select('user_id', 'school_id', 'role')->get();
        $now = now();

        $buckets = ['admin' => [], 'teacher' => [], 'student' => []];

        foreach ($rows as $r) {
            // Legacy super_admin memberships are migrated separately, not into a profile.
            $role = $r->role === 'super_admin' ? 'admin' : $r->role;
            if (! isset($buckets[$role])) {
                continue;
            }

            $buckets[$role][] = [
                'id' => (string) Illuminate\Support\Str::uuid(),
                'user_id' => $r->user_id,
                'school_id' => $r->school_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($buckets['admin'] as $i => $row) {
            $buckets['admin'][$i]['is_primary'] = $i === 0;
        }
        foreach ($buckets['student'] as $i => $row) {
            $buckets['student'][$i]['status'] = 'active';
        }
        foreach ($buckets['teacher'] as $i => $row) {
            $buckets['teacher'][$i]['employment_type'] = 'full_time';
        }

        foreach (['admin' => 'admin_profiles', 'teacher' => 'teacher_profiles', 'student' => 'student_profiles'] as $role => $table) {
            foreach (array_chunk($buckets[$role], 200) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }
};
