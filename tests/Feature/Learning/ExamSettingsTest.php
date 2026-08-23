<?php

namespace Tests\Feature\Learning;

use App\Models\ClassArm;
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
 * Exam settings on the create and edit forms.
 *
 * Only settings the app enforces are offered: duration drives the countdown in
 * the exam runner, max_attempts is checked before a new attempt starts, and
 * pass_mark decides the pass flag on submit. The subject matters because it is
 * what the question-bank picker filters on, so it is scoped to the subjects the
 * teacher is actually assigned to.
 */
class ExamSettingsTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Subject $maths;

    private Subject $chemistry;

    private User $mathsTeacher;

    private ClassArm $class;

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
            'name' => 'Year 7', 'code' => 'y7', 'position' => 1,
        ]);

        $this->class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $this->maths = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $this->chemistry = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Chemistry', 'code' => 'CHM',
        ]);

        $this->mathsTeacher = $this->user('maths@test.test', SchoolMember::ROLE_TEACHER);

        ClassSubjectAssignment::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $this->class->id,
            'subject_id' => $this->maths->id,
            'teacher_id' => $this->mathsTeacher->id,
        ]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => 'Person',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $this->school->id, 'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    public function test_duration_is_saved_rather_than_silently_dropped(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Mid-term',
                'duration' => 45,
            ])->assertRedirect();

        $exam = Exam::where('title', 'Mid-term')->first();

        $this->assertNotNull($exam);
        $this->assertSame(45, $exam->duration, 'The countdown timer reads exams.duration.');
    }

    public function test_settings_fall_back_to_sensible_defaults(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), ['title' => 'Pop quiz'])
            ->assertRedirect();

        $exam = Exam::where('title', 'Pop quiz')->first();

        $this->assertEquals(50, $exam->pass_mark);
        $this->assertEquals(1, $exam->max_attempts);
        $this->assertNull($exam->duration, 'No duration means untimed.');
    }

    public function test_max_attempts_and_pass_mark_are_stored(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Retakeable',
                'pass_mark' => 70,
                'max_attempts' => 3,
            ])->assertRedirect();

        $exam = Exam::where('title', 'Retakeable')->first();

        $this->assertEquals(70, $exam->pass_mark);
        $this->assertEquals(3, $exam->max_attempts);
    }

    public function test_teacher_can_tie_an_exam_to_a_subject_they_teach(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Algebra test',
                'subject_id' => $this->maths->id,
            ])->assertRedirect();

        $this->assertSame(
            $this->maths->id,
            Exam::where('title', 'Algebra test')->first()->subject_id
        );
    }

    public function test_teacher_cannot_tie_an_exam_to_a_subject_they_do_not_teach(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Chemistry test',
                'subject_id' => $this->chemistry->id,
            ])->assertSessionHasErrors('subject_id');

        $this->assertDatabaseMissing('exams', ['title' => 'Chemistry test']);
    }

    public function test_create_form_only_offers_assigned_subjects(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertSee('Maths')
            ->assertDontSee('Chemistry');
    }

    public function test_unenforced_settings_are_not_offered_as_dead_switches(): void
    {
        $response = $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.create'))
            ->assertOk();

        // Nothing reads these columns yet, so showing them would promise
        // behaviour the exam runner does not deliver.
        $response->assertDontSee('name="shuffle_questions"', false);
        $response->assertDontSee('name="shuffle_options"', false);
        $response->assertDontSee('name="negative_marking"', false);
    }

    public function test_duration_is_bounded(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Endless',
                'duration' => 0,
            ])->assertSessionHasErrors('duration');
    }

    public function test_editing_updates_settings_and_keeps_the_duration(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Draft exam',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
            'duration' => 30,
            'pass_mark' => 50,
            'max_attempts' => 1,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.exams.update', $exam), [
                'title' => 'Draft exam',
                'duration' => 90,
                'pass_mark' => 65,
                'max_attempts' => 2,
            ])->assertRedirect();

        $exam->refresh();

        $this->assertSame(90, $exam->duration);
        $this->assertEquals(65, $exam->pass_mark);
        $this->assertEquals(2, $exam->max_attempts);
    }
}
