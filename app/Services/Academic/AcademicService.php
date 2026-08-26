<?php

namespace App\Services\Academic;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operations on the academic structure that carry real rules — enrollment
 * capacity, one-teacher-per-subject, end-of-session promotion.
 *
 * Controllers stay thin; the invariants live here.
 */
class AcademicService
{
    // ── Enrollment ──

    /**
     * Enroll a student into an arm.
     *
     * @throws ValidationException when the arm is full or the user is not a
     *                             student of this school.
     */
    public function enroll(ClassArm $arm, User $student): ClassEnrollment
    {
        if (! $student->belongsToSchool($arm->school_id)) {
            throw ValidationException::withMessages([
                'user_id' => 'That user does not belong to this school.',
            ]);
        }

        $existing = ClassEnrollment::where('class_arm_id', $arm->id)
            ->where('user_id', $student->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($arm->isFull()) {
            throw ValidationException::withMessages([
                'user_id' => $arm->fullName().' is at capacity ('.$arm->capacity.' students).',
            ]);
        }

        return ClassEnrollment::create([
            'class_arm_id' => $arm->id,
            'user_id' => $student->id,
            'role' => 'student',
            'enrolled_at' => now(),
        ]);
    }

    /** Move a student from one arm to another, preserving enrollment history. */
    public function transfer(User $student, ClassArm $from, ClassArm $to): ClassEnrollment
    {
        return DB::transaction(function () use ($student, $from, $to) {
            ClassEnrollment::where('class_arm_id', $from->id)
                ->where('user_id', $student->id)
                ->delete();

            return $this->enroll($to, $student);
        });
    }

    // ── Subject assignments ──

    /**
     * Assign a teacher to a subject in an arm. Re-assigning replaces the
     * existing teacher rather than erroring — the unique constraint on
     * (class_arm_id, subject_id) guarantees only one can exist.
     */
    public function assignTeacher(ClassArm $arm, Subject $subject, User $teacher): ClassSubjectAssignment
    {
        if ($teacher->roleInSchool() !== SchoolMember::ROLE_TEACHER
            && $teacher->roleInSchool() !== SchoolMember::ROLE_ADMIN) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Only teachers can be assigned to a subject.',
            ]);
        }

        if ($subject->school_id !== $arm->school_id) {
            throw ValidationException::withMessages([
                'subject_id' => 'That subject belongs to a different school.',
            ]);
        }

        return ClassSubjectAssignment::updateOrCreate(
            ['class_arm_id' => $arm->id, 'subject_id' => $subject->id],
            ['school_id' => $arm->school_id, 'teacher_id' => $teacher->id]
        );
    }

    public function unassignTeacher(ClassArm $arm, Subject $subject): void
    {
        ClassSubjectAssignment::where('class_arm_id', $arm->id)
            ->where('subject_id', $subject->id)
            ->delete();
    }

    /**
     * Subjects that apply to an arm's level, with the assigned teacher (if any).
     *
     * @return Collection<int, array{subject: Subject, assignment: ?ClassSubjectAssignment}>
     */
    public function subjectMatrix(ClassArm $arm): Collection
    {
        $levelCode = $arm->classLevel?->code ?? '';

        $assignments = $arm->subjectAssignments()
            ->with('teacher')
            ->get()
            ->keyBy('subject_id');

        return Subject::where('school_id', $arm->school_id)
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (Subject $s) => $s->appliesToLevel($levelCode))
            ->map(fn (Subject $s) => [
                'subject' => $s,
                'assignment' => $assignments->get($s->id),
            ])
            ->values();
    }

    // ── Promotion ──

    /**
     * Promote every student in an arm to a target arm in the next level.
     *
     * Returns a per-student outcome so the caller can report partial success —
     * a full destination arm should not abort the whole batch.
     *
     * @return array{promoted: int, skipped: array<int, string>}
     */
    public function promoteArm(ClassArm $from, ClassArm $to): array
    {
        $promoted = 0;
        $skipped = [];

        $enrollments = $from->enrollments()->with('user')->get();

        DB::transaction(function () use ($enrollments, $from, $to, &$promoted, &$skipped) {
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->user;

                if (! $student) {
                    continue;
                }

                try {
                    $this->transfer($student, $from, $to);
                    $promoted++;
                } catch (ValidationException $e) {
                    $skipped[] = $student->name.' — '.collect($e->errors())->flatten()->first();
                }
            }
        });

        return ['promoted' => $promoted, 'skipped' => $skipped];
    }

    /** Suggested destination arm when promoting: same arm name, next level. */
    public function suggestedPromotionTarget(ClassArm $arm): ?ClassArm
    {
        $nextLevel = $arm->classLevel?->nextLevel();

        if (! $nextLevel) {
            return null;
        }

        return ClassArm::where('class_level_id', $nextLevel->id)
            ->where('name', $arm->name)
            ->first()
            ?? $nextLevel->arms()->first();
    }

    // ── Structure summary ──

    /**
     * Counts used by the admin academic overview.
     *
     * @return array<string, int|bool>
     */
    public function summary(School $school): array
    {
        return [
            'levels' => ClassLevel::where('school_id', $school->id)->count(),
            'arms' => ClassArm::where('school_id', $school->id)->count(),
            'subjects' => Subject::where('school_id', $school->id)->active()->count(),
            'assignments' => ClassSubjectAssignment::where('school_id', $school->id)->count(),
            'unassigned' => $this->unassignedCount($school),
            'weights_balance' => \App\Models\AssessmentType::weightsBalance($school->id),
        ];
    }

    /** (arm × subject) pairs that still have no teacher. */
    public function unassignedCount(School $school): int
    {
        $arms = ClassArm::where('school_id', $school->id)->with('classLevel')->get();
        $subjects = Subject::where('school_id', $school->id)->active()->get();

        $assigned = ClassSubjectAssignment::where('school_id', $school->id)
            ->get()
            ->map(fn ($a) => $a->class_arm_id.'|'.$a->subject_id)
            ->flip();

        $missing = 0;

        foreach ($arms as $arm) {
            $levelCode = $arm->classLevel?->code ?? '';

            foreach ($subjects as $subject) {
                if (! $subject->appliesToLevel($levelCode)) {
                    continue;
                }

                if (! $assigned->has($arm->id.'|'.$subject->id)) {
                    $missing++;
                }
            }
        }

        return $missing;
    }
}
