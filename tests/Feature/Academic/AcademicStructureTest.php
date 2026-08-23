<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\AssessmentType;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academic\AcademicService;
use App\Services\Academic\SchoolBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The academic structure carries most of the app's real invariants:
 * one teacher per subject per class, capacity limits, a single current
 * term/session, and promotion between levels. These cover them.
 */
class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'subdomain' => 'test',
            'status' => School::STATUS_ACTIVE,
        ]);

        // Several helpers resolve the current role via app('tenant'); the
        // middleware normally binds it.
        app()->instance('tenant', $this->school);
    }

    private function service(): AcademicService
    {
        return app(AcademicService::class);
    }

    private function level(string $code, string $name, int $position): ClassLevel
    {
        return ClassLevel::create([
            'school_id' => $this->school->id,
            'name' => $name,
            'code' => $code,
            'position' => $position,
        ]);
    }

    private function arm(ClassLevel $level, string $name, int $capacity = 40): ClassArm
    {
        return ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id,
            'name' => $name,
            'capacity' => $capacity,
        ]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => ucfirst($role).' '.substr(md5($email), 0, 4),
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    // ───────────────────────── bootstrapping ─────────────────────────

    public function test_bootstrapper_creates_a_usable_structure(): void
    {
        app(SchoolBootstrapper::class)->bootstrap($this->school);
        $this->school->refresh();

        $preset = config('academic.presets.'.config('academic.preset'));

        $this->assertSame(1, AcademicSession::where('school_id', $this->school->id)->count());
        $this->assertCount(count($preset['terms']), Term::where('school_id', $this->school->id)->get());
        $this->assertCount(count($preset['levels']), ClassLevel::where('school_id', $this->school->id)->get());
        $this->assertCount(count($preset['subjects']), Subject::where('school_id', $this->school->id)->get());

        $this->assertNotNull($this->school->current_session_id, 'A current session should be set.');
        $this->assertNotNull($this->school->current_term_id, 'A current term should be set.');
    }

    public function test_bootstrapper_is_idempotent(): void
    {
        app(SchoolBootstrapper::class)->bootstrap($this->school);
        $levels = ClassLevel::where('school_id', $this->school->id)->count();

        app(SchoolBootstrapper::class)->bootstrap($this->school);

        $this->assertSame($levels, ClassLevel::where('school_id', $this->school->id)->count());
    }

    public function test_preset_assessment_weights_total_one_hundred(): void
    {
        app(SchoolBootstrapper::class)->bootstrap($this->school);

        $this->assertTrue(
            AssessmentType::weightsBalance($this->school->id),
            'Seeded assessment weights should total 100%.'
        );
    }

    // ───────────────────────── current session / term ─────────────────────────

    public function test_only_one_term_can_be_current(): void
    {
        $session = AcademicSession::create([
            'school_id' => $this->school->id,
            'name' => '2024/2025',
        ]);

        $first = Term::create([
            'school_id' => $this->school->id,
            'academic_session_id' => $session->id,
            'name' => 'First', 'sequence' => 1,
        ]);
        $second = Term::create([
            'school_id' => $this->school->id,
            'academic_session_id' => $session->id,
            'name' => 'Second', 'sequence' => 2,
        ]);

        $first->makeCurrent();
        $second->makeCurrent();

        $this->assertFalse($first->fresh()->is_current);
        $this->assertTrue($second->fresh()->is_current);
        $this->assertSame($second->id, $this->school->fresh()->current_term_id);
    }

    // ───────────────────────── enrollment ─────────────────────────

    public function test_enrollment_respects_capacity(): void
    {
        $level = $this->level('y7', 'Year 7', 1);
        $arm = $this->arm($level, 'A', capacity: 1);

        $this->service()->enroll($arm, $this->user('a@test.test', SchoolMember::ROLE_STUDENT));

        $this->expectException(ValidationException::class);
        $this->service()->enroll($arm, $this->user('b@test.test', SchoolMember::ROLE_STUDENT));
    }

    public function test_enrolling_twice_is_a_no_op(): void
    {
        $arm = $this->arm($this->level('y7', 'Year 7', 1), 'A');
        $student = $this->user('a@test.test', SchoolMember::ROLE_STUDENT);

        $first = $this->service()->enroll($arm, $student);
        $second = $this->service()->enroll($arm, $student);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $arm->enrollments()->count());
    }

    public function test_cannot_enroll_a_user_from_another_school(): void
    {
        $other = School::create([
            'name' => 'Other', 'slug' => 'other', 'subdomain' => 'other',
            'status' => School::STATUS_ACTIVE,
        ]);

        $outsider = User::create([
            'school_id' => $other->id,
            'name' => 'Outsider',
            'email' => 'out@other.test',
            'password' => Hash::make('password'),
        ]);

        $arm = $this->arm($this->level('y7', 'Year 7', 1), 'A');

        $this->expectException(ValidationException::class);
        $this->service()->enroll($arm, $outsider);
    }

    // ───────────────────────── subject assignment ─────────────────────────

    public function test_a_subject_has_only_one_teacher_per_arm(): void
    {
        $arm = $this->arm($this->level('y7', 'Year 7', 1), 'A');
        $subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $teacherA = $this->user('t1@test.test', SchoolMember::ROLE_TEACHER);
        $teacherB = $this->user('t2@test.test', SchoolMember::ROLE_TEACHER);

        $this->service()->assignTeacher($arm, $subject, $teacherA);
        $this->service()->assignTeacher($arm, $subject, $teacherB);

        $assignments = ClassSubjectAssignment::where('class_arm_id', $arm->id)
            ->where('subject_id', $subject->id)
            ->get();

        $this->assertCount(1, $assignments, 'Re-assigning should replace, not duplicate.');
        $this->assertSame($teacherB->id, $assignments->first()->teacher_id);
    }

    public function test_students_cannot_be_assigned_to_teach(): void
    {
        $arm = $this->arm($this->level('y7', 'Year 7', 1), 'A');
        $subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->assignTeacher($arm, $subject, $this->user('s@test.test', SchoolMember::ROLE_STUDENT));
    }

    public function test_subject_matrix_only_lists_applicable_subjects(): void
    {
        $junior = $this->level('y7', 'Year 7', 1);
        $arm = $this->arm($junior, 'A');

        Subject::create([
            'school_id' => $this->school->id, 'name' => 'Everywhere', 'code' => 'ALL',
        ]);
        Subject::create([
            'school_id' => $this->school->id, 'name' => 'Seniors only', 'code' => 'SR',
            'applies_to' => ['y12'],
        ]);

        $matrix = $this->service()->subjectMatrix($arm);

        $this->assertCount(1, $matrix);
        $this->assertSame('Everywhere', $matrix->first()['subject']->name);
    }

    // ───────────────────────── promotion ─────────────────────────

    public function test_promotion_moves_students_to_the_next_level(): void
    {
        $y7 = $this->level('y7', 'Year 7', 1);
        $y8 = $this->level('y8', 'Year 8', 2);

        $from = $this->arm($y7, 'A');
        $to = $this->arm($y8, 'A');

        foreach (['a', 'b', 'c'] as $n) {
            $this->service()->enroll($from, $this->user("$n@test.test", SchoolMember::ROLE_STUDENT));
        }

        $result = $this->service()->promoteArm($from, $to);

        $this->assertSame(3, $result['promoted']);
        $this->assertEmpty($result['skipped']);
        $this->assertSame(0, $from->fresh()->enrollments()->count());
        $this->assertSame(3, $to->fresh()->enrollments()->count());
    }

    public function test_promotion_reports_students_it_could_not_move(): void
    {
        $y7 = $this->level('y7', 'Year 7', 1);
        $y8 = $this->level('y8', 'Year 8', 2);

        $from = $this->arm($y7, 'A');
        $to = $this->arm($y8, 'A', capacity: 1);

        foreach (['a', 'b'] as $n) {
            $this->service()->enroll($from, $this->user("$n@test.test", SchoolMember::ROLE_STUDENT));
        }

        $result = $this->service()->promoteArm($from, $to);

        $this->assertSame(1, $result['promoted']);
        $this->assertCount(1, $result['skipped'], 'The student who did not fit should be reported.');

        // The student who could not be promoted must still be in the old arm,
        // not dropped on the floor between the two.
        $this->assertSame(1, $from->fresh()->enrollments()->count());
    }

    public function test_suggested_promotion_target_prefers_the_same_arm_name(): void
    {
        $y7 = $this->level('y7', 'Year 7', 1);
        $y8 = $this->level('y8', 'Year 8', 2);

        $from = $this->arm($y7, 'B');
        $this->arm($y8, 'A');
        $expected = $this->arm($y8, 'B');

        $this->assertSame($expected->id, $this->service()->suggestedPromotionTarget($from)?->id);
    }

    public function test_top_level_has_no_promotion_target(): void
    {
        $top = $this->level('y12', 'Year 12', 12);

        $this->assertNull($this->service()->suggestedPromotionTarget($this->arm($top, 'A')));
    }

    // ───────────────────────── misc ─────────────────────────

    public function test_arm_gets_a_unique_invite_code(): void
    {
        $level = $this->level('y7', 'Year 7', 1);

        $a = $this->arm($level, 'A');
        $b = $this->arm($level, 'B');

        $this->assertNotEmpty($a->invite_code);
        $this->assertNotSame($a->invite_code, $b->invite_code);
    }

    public function test_full_name_combines_level_and_arm(): void
    {
        $arm = $this->arm($this->level('y10', 'Year 10', 10), 'B');

        $this->assertSame('Year 10 B', $arm->fresh()->load('classLevel')->fullName());
    }

    public function test_unassigned_count_reflects_missing_teachers(): void
    {
        $level = $this->level('y7', 'Year 7', 1);
        $arm = $this->arm($level, 'A');

        $maths = Subject::create(['school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH']);
        Subject::create(['school_id' => $this->school->id, 'name' => 'English', 'code' => 'ENG']);

        $this->assertSame(2, $this->service()->unassignedCount($this->school));

        $this->service()->assignTeacher($arm, $maths, $this->user('t@test.test', SchoolMember::ROLE_TEACHER));

        $this->assertSame(1, $this->service()->unassignedCount($this->school));
    }
}
