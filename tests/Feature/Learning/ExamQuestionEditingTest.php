<?php

namespace Tests\Feature\Learning;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Writing and editing questions on an exam.
 *
 * The exam form reuses the bank's shaping, so a question written here is
 * stored exactly like one banked from a study guide: the answer as text, blank
 * options dropped, and written types carrying no options at all.
 */
class ExamQuestionEditingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    private Exam $exam;

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

        $this->teacher = User::create([
            'school_id' => $this->school->id,
            'name' => 'Teacher',
            'email' => 'teacher@test.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $this->teacher->id,
            'school_id' => $this->school->id,
            'role' => SchoolMember::ROLE_TEACHER,
        ]);

        $this->teacher = $this->teacher->fresh('memberships');

        $this->exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Geography',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->teacher->id,
        ]);
    }

    public function test_a_choice_question_stores_the_answer_as_text(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Capital of France?',
                'type' => 'mcq',
                'options' => ['London', 'Paris', 'Rome'],
                'correct_idx' => 1,
                'points' => 2,
            ])->assertRedirect();

        $q = ExamQuestion::where('exam_id', $this->exam->id)->first();

        $this->assertNotNull($q, 'The question should have been created.');
        $this->assertSame('Paris', $q->answer, 'The answer is stored as text, not a position.');
        $this->assertSame(['London', 'Paris', 'Rome'], $q->options);
        $this->assertEquals(2, $q->points);
    }

    public function test_blank_options_are_dropped_and_the_answer_still_resolves(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Capital of France?',
                'type' => 'mcq',
                'options' => ['London', 'Paris', '', '   '],
                'correct_idx' => 1,
            ])->assertRedirect();

        $q = ExamQuestion::where('exam_id', $this->exam->id)->first();

        $this->assertSame(['London', 'Paris'], $q->options);
        $this->assertSame('Paris', $q->answer);
    }

    public function test_a_written_question_keeps_no_options(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Name the longest river in Africa.',
                'type' => 'short_answer',
                'answer' => 'The Nile',
            ])->assertRedirect();

        $q = ExamQuestion::where('exam_id', $this->exam->id)->first();

        $this->assertNull($q->options);
        $this->assertSame('The Nile', $q->answer);
    }

    public function test_a_written_question_needs_an_answer(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Name the longest river in Africa.',
                'type' => 'short_answer',
                'answer' => '   ',
            ])->assertSessionHasErrors('answer');

        $this->assertSame(0, ExamQuestion::where('exam_id', $this->exam->id)->count());
    }

    public function test_a_choice_question_needs_two_options(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Capital of France?',
                'type' => 'mcq',
                'options' => ['Paris', ''],
                'correct_idx' => 0,
            ])->assertSessionHasErrors('options');

        $this->assertSame(0, ExamQuestion::where('exam_id', $this->exam->id)->count());
    }

    public function test_true_false_is_normalised(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'The Nile flows north.',
                'type' => 'true_false',
                'correct_idx' => 0,
            ])->assertRedirect();

        $q = ExamQuestion::where('exam_id', $this->exam->id)->first();

        $this->assertSame(['True', 'False'], $q->options);
        $this->assertSame('True', $q->answer);
    }

    public function test_questions_are_appended_rather_than_colliding_on_order(): void
    {
        // count()+1 repeats an order once anything has been removed.
        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'First', 'type' => 'mcq',
            'options' => ['A', 'B'], 'answer' => 'A',
            'points' => 1, 'order' => 7,
        ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Second',
                'type' => 'mcq',
                'options' => ['A', 'B'],
                'correct_idx' => 0,
            ])->assertRedirect();

        $this->assertSame(8, ExamQuestion::where('question', 'Second')->first()->order);
    }

    public function test_a_question_can_be_edited(): void
    {
        $q = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'Capital of France?', 'type' => 'mcq',
            'options' => ['London', 'Paris'], 'answer' => 'Paris',
            'points' => 1, 'order' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->put(route('teacher.exams.questions.update', [$this->exam, $q]), [
                'question' => 'Which city is the capital of France?',
                'type' => 'mcq',
                'options' => ['London', 'Paris', 'Rome'],
                'correct_idx' => 2,
                'points' => 3,
            ])->assertRedirect();

        $q->refresh();

        $this->assertSame('Which city is the capital of France?', $q->question);
        $this->assertSame('Rome', $q->answer);
        $this->assertEquals(3, $q->points);
    }

    public function test_switching_to_a_written_type_clears_the_options(): void
    {
        $q = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'Capital of France?', 'type' => 'mcq',
            'options' => ['London', 'Paris'], 'answer' => 'Paris',
            'points' => 1, 'order' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->put(route('teacher.exams.questions.update', [$this->exam, $q]), [
                'question' => 'Capital of France?',
                'type' => 'short_answer',
                'answer' => 'Paris',
            ])->assertRedirect();

        $q->refresh();

        $this->assertNull($q->options);
        $this->assertSame('short_answer', $q->type);
    }

    public function test_a_question_cannot_be_edited_through_another_exam(): void
    {
        $other = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Other exam',
            'status' => Exam::STATUS_DRAFT,
            'created_by' => $this->teacher->id,
        ]);

        $q = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'Capital of France?', 'type' => 'mcq',
            'options' => ['London', 'Paris'], 'answer' => 'Paris',
            'points' => 1, 'order' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->put(route('teacher.exams.questions.update', [$other, $q]), [
                'question' => 'Tampered',
                'type' => 'mcq',
                'options' => ['A', 'B'],
                'correct_idx' => 0,
            ])->assertNotFound();

        $this->assertSame('Capital of France?', $q->fresh()->question);
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.store', $this->exam), [
                'question' => 'Capital of France?',
                'type' => 'freeform',
                'answer' => 'Paris',
            ])->assertSessionHasErrors('type');
    }
}
