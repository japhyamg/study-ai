<?php

namespace Tests\Feature\Learning;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
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

    public function test_negative_marking_is_not_offered_because_nothing_applies_it(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertDontSee('name="negative_marking"', false);
    }

    public function test_the_availability_window_is_saved(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Scheduled',
                'start_time' => '2026-09-01T09:00',
                'end_time' => '2026-09-01T11:00',
            ])->assertRedirect();

        $exam = Exam::where('title', 'Scheduled')->first();

        $this->assertSame('2026-09-01 09:00', $exam->start_time->format('Y-m-d H:i'));
        $this->assertSame('2026-09-01 11:00', $exam->end_time->format('Y-m-d H:i'));
    }

    public function test_the_window_cannot_close_before_it_opens(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Backwards',
                'start_time' => '2026-09-01T11:00',
                'end_time' => '2026-09-01T09:00',
            ])->assertSessionHasErrors('end_time');

        $this->assertDatabaseMissing('exams', ['title' => 'Backwards']);
    }

    public function test_shuffle_toggles_are_saved(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.exams.store'), [
                'title' => 'Shuffled',
                'shuffle_questions' => '1',
                'shuffle_options' => '1',
            ])->assertRedirect();

        $exam = Exam::where('title', 'Shuffled')->first();

        $this->assertTrue($exam->shuffle_questions);
        $this->assertTrue($exam->shuffle_options);
    }

    public function test_unticking_a_shuffle_box_turns_it_off_again(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Shuffled',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
            'shuffle_questions' => true,
            'shuffle_options' => true,
        ]);

        // An unticked checkbox sends nothing at all, so the update has to write
        // false rather than leave the old value in place.
        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.exams.update', $exam), ['title' => 'Shuffled'])
            ->assertRedirect();

        $exam->refresh();

        $this->assertFalse($exam->shuffle_questions);
        $this->assertFalse($exam->shuffle_options);
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

    public function test_a_teacher_can_delete_an_exam_they_created(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Mistake',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->delete(route('teacher.exams.destroy', $exam))
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    public function test_a_teacher_cannot_delete_someone_elses_exam(): void
    {
        $other = $this->user('other@test.test', SchoolMember::ROLE_TEACHER);

        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Not yours',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $other->id,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->delete(route('teacher.exams.destroy', $exam))
            ->assertForbidden();

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_an_exam_students_have_sat_is_not_deleted(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Already sat',
            'status' => Exam::STATUS_PUBLISHED,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $student = $this->user('sat@test.test', SchoolMember::ROLE_STUDENT);

        // exam_attempts cascades, so deleting here would destroy the results.
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'submitted' => true,
            'answers' => [],
        ]);

        $this->actingAs($this->mathsTeacher)
            ->delete(route('teacher.exams.destroy', $exam))
            ->assertSessionHasErrors('exam');

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_an_unfinished_attempt_does_not_block_deletion(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Abandoned',
            'status' => Exam::STATUS_PUBLISHED,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $student = $this->user('abandoned@test.test', SchoolMember::ROLE_STUDENT);

        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'submitted' => false,
            'answers' => [],
        ]);

        $this->actingAs($this->mathsTeacher)
            ->delete(route('teacher.exams.destroy', $exam))
            ->assertRedirect(route('teacher.exams.index'));

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    public function test_the_exam_page_shows_the_details_instead_of_the_topbar(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Mid-term',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
            'duration' => 45,
            'pass_mark' => 60,
            'max_attempts' => 2,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.show', $exam))
            ->assertOk()
            ->assertSee('Exam setup')
            ->assertSee('Edit settings')
            // The duration, pass mark and attempt count are on the page now,
            // not hidden behind the edit form.
            ->assertSee('45 min')
            ->assertSee('Attempts')
            // The delete control is an icon button; its label is the title.
            ->assertSee('title="Delete exam"', false)
            // Every exam page needs a way back to the list.
            ->assertSee(route('teacher.exams.index'), false);
    }

    public function test_the_list_shows_a_real_question_count(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Mid-term',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
        ]);

        foreach (range(1, 3) as $i) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question' => "Question {$i}",
                'type' => 'mcq',
                'options' => ['A', 'B'],
                'answer' => 'A',
                'points' => 1,
                'order' => $i,
            ]);
        }

        // questions_count was never loaded, so the column rendered blank.
        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.index'))
            ->assertOk()
            ->assertSee('>3<', false)
            ->assertSee('questions');
    }

    public function test_the_list_counts_only_submitted_attempts(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Mid-term',
            'status' => Exam::STATUS_PUBLISHED,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $student = $this->user('sat2@test.test', SchoolMember::ROLE_STUDENT);

        ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id,
            'submitted' => true, 'answers' => [],
        ]);

        ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id,
            'submitted' => false, 'answers' => [],
        ]);

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.index'))
            ->assertOk()
            // One sat it; the abandoned attempt is not a result.
            ->assertSee('>1<', false)
            ->assertSee('student');
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        Exam::create([
            'school_id' => $this->school->id,
            'title' => 'A draft exam',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
        ]);

        Exam::create([
            'school_id' => $this->school->id,
            'title' => 'A published exam',
            'status' => Exam::STATUS_PUBLISHED,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee('A published exam')
            ->assertDontSee('A draft exam');
    }

    public function test_an_unknown_status_filter_is_ignored(): void
    {
        Exam::create([
            'school_id' => $this->school->id,
            'title' => 'A draft exam',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.index', ['status' => 'nonsense']))
            ->assertOk()
            ->assertSee('A draft exam');
    }

    public function test_the_create_and_edit_pages_link_back(): void
    {
        $exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Mid-term',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->mathsTeacher->id,
        ]);

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.create'))
            ->assertOk()
            ->assertSee(route('teacher.exams.index'), false);

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.exams.edit', $exam))
            ->assertOk()
            ->assertSee(route('teacher.exams.show', $exam), false);
    }
}

