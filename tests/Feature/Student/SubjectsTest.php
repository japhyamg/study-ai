<?php

namespace Tests\Feature\Student;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\Exam;
use App\Models\Material;
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

    /** A published guide for a subject. */
    private function guide(string $title, ?Subject $subject): Material
    {
        return Material::create([
            'school_id' => $this->school->id,
            'subject_id' => $subject?->id,
            'created_by' => $this->teacher->id,
            'title' => $title,
            'type' => 'note',
            'content' => 'Lesson content.',
            'workflow_state' => Material::STATE_PUBLISHED,
        ]);
    }

    public function test_a_subject_page_lists_its_study_guides(): void
    {
        $this->guide('Algebra basics', $this->maths);
        $this->guide('Organic chemistry', $this->chemistry);

        $this->actingAs($this->student)
            ->get(route('student.subjects.show', $this->maths))
            ->assertOk()
            ->assertSee('Algebra basics')
            ->assertDontSee('Organic chemistry');
    }

    public function test_the_study_guide_list_is_scoped_to_taught_subjects(): void
    {
        $this->guide('Algebra basics', $this->maths);
        $this->guide('Organic chemistry', $this->chemistry);

        $this->actingAs($this->student)
            ->get(route('student.study.index'))
            ->assertOk()
            ->assertSee('Algebra basics')
            ->assertDontSee('Organic chemistry');
    }

    public function test_a_guide_with_no_subject_stays_visible(): void
    {
        // School-wide material would otherwise disappear from the list.
        $this->guide('Study skills', null);

        $this->actingAs($this->student)
            ->get(route('student.study.index'))
            ->assertOk()
            ->assertSee('Study skills');
    }

    public function test_a_guide_without_flashcards_is_still_listed(): void
    {
        // The old list hid these, so a teacher's guide vanished until someone
        // generated cards for it.
        $this->guide('Algebra basics', $this->maths);

        $this->actingAs($this->student)
            ->get(route('student.study.index'))
            ->assertOk()
            ->assertSee('Algebra basics');
    }

    public function test_a_guide_for_an_untaught_subject_cannot_be_opened(): void
    {
        $guide = $this->guide('Organic chemistry', $this->chemistry);

        $this->actingAs($this->student)
            ->get(route('student.study.hub', $guide))
            ->assertNotFound();
    }

    public function test_a_guide_for_a_taught_subject_opens_with_its_three_tabs(): void
    {
        $guide = $this->guide('Algebra basics', $this->maths);

        $this->actingAs($this->student)
            ->get(route('student.study.hub', $guide))
            ->assertOk()
            ->assertSee('Study guide')
            ->assertSee('Flashcards')
            ->assertSee('Quiz');
    }

    public function test_an_unpublished_guide_is_not_listed(): void
    {
        $draft = $this->guide('Draft notes', $this->maths);
        $draft->update(['workflow_state' => Material::STATE_DRAFT]);

        $this->actingAs($this->student)
            ->get(route('student.study.index'))
            ->assertOk()
            ->assertDontSee('Draft notes');
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
