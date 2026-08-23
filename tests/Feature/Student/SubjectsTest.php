<?php

namespace Tests\Feature\Student;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\Exam;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The student's subject list.
 *
 * A student belongs to a class arm, and each arm is assigned its subjects. The
 * arm is how the school groups them; the subject is what they study, so the
 * menu is built from the assignments against their own arms.
 */
class SubjectsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $student;

    private User $teacher;

    private ClassArm $class;

    private Subject $maths;

    private Subject $chemistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'subdomain' => 'test',
            'status' => School::STATUS_ACTIVE,
        ]);

        app()->instance('tenant', $this->school);

        $level = ClassLevel::create([
            'school_id' => $this->school->id,
            'name' => 'JSS 1', 'code' => 'jss1', 'position' => 1,
        ]);

        $this->class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $this->maths = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Mathematics', 'code' => 'MTH',
        ]);

        $this->chemistry = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Chemistry', 'code' => 'CHM',
        ]);

        $this->teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');
        $this->student = $this->user('student@test.test', SchoolMember::ROLE_STUDENT, 'Sade Student');

        ClassEnrollment::create([
            'class_arm_id' => $this->class->id,
            'user_id' => $this->student->id,
            'role' => SchoolMember::ROLE_STUDENT,
        ]);

        ClassSubjectAssignment::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $this->class->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    private function user(string $email, string $role, string $name): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $this->school->id, 'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    public function test_the_list_shows_subjects_the_student_is_taught(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.subjects'))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Tunde Teacher');
    }

    public function test_subjects_not_taught_to_their_class_are_hidden(): void
    {
        // Chemistry exists at the school but is not assigned to this arm.
        $this->actingAs($this->student)
            ->get(route('student.subjects'))
            ->assertOk()
            ->assertDontSee('Chemistry');
    }

    public function test_a_subject_page_opens(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.subjects.show', $this->maths))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Tunde Teacher');
    }

    public function test_a_subject_they_are_not_taught_is_not_reachable(): void
    {
        // Route binding resolves any subject, so the assignment is the guard.
        $this->actingAs($this->student)
            ->get(route('student.subjects.show', $this->chemistry))
            ->assertNotFound();
    }

    public function test_a_subject_page_lists_its_published_exams(): void
    {
        Exam::create([
            'school_id' => $this->school->id,
            'subject_id' => $this->maths->id,
            'class_arm_id' => $this->class->id,
            'title' => 'Algebra test',
            'status' => Exam::STATUS_PUBLISHED,
        ]);

        Exam::create([
            'school_id' => $this->school->id,
            'subject_id' => $this->maths->id,
            'class_arm_id' => $this->class->id,
            'title' => 'Unfinished draft',
            'status' => Exam::STATUS_DRAFT,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.subjects.show', $this->maths))
            ->assertOk()
            ->assertSee('Algebra test')
            ->assertDontSee('Unfinished draft');
    }

    public function test_a_subject_appears_once_even_with_two_assignments(): void
    {
        $second = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $this->class->class_level_id, 'name' => 'B',
        ]);

        ClassEnrollment::create([
            'class_arm_id' => $second->id,
            'user_id' => $this->student->id,
            'role' => SchoolMember::ROLE_STUDENT,
        ]);

        ClassSubjectAssignment::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $second->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($this->student)
            ->get(route('student.subjects'))
            ->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), 'Mathematics'));
    }

    public function test_the_dashboard_lists_subjects_rather_than_classes(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Your subjects')
            ->assertSee('Mathematics');
    }

    public function test_the_removed_student_pages_are_gone(): void
    {
        // Flashcards, topics, classes and materials were taken off the menu and
        // their routes removed; Study is the practice entry point now.
        foreach (['student/flashcards', 'student/topics', 'student/classes', 'student/materials'] as $path) {
            $this->actingAs($this->student)->get('/'.$path)->assertNotFound();
        }
    }
}
