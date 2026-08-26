<?php

namespace Tests\Feature\Learning;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\User;
use App\Services\Learning\ExamPaperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Shuffling, the availability window, and grading.
 *
 * These belong together: the moment options can be reordered, an answer
 * recorded as "option 2" stops meaning anything, so the paper is shuffled per
 * attempt and every answer is stored and graded as text.
 */
class ExamPaperTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $student;

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

        $this->student = User::create([
            'school_id' => $this->school->id,
            'name' => 'Student',
            'email' => 'student@test.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'role' => SchoolMember::ROLE_STUDENT,
        ]);

        $this->student = $this->student->fresh('memberships');

        $this->exam = Exam::create([
            'school_id' => $this->school->id,
            'title' => 'Geography',
            'status' => Exam::STATUS_PUBLISHED,
            'pass_mark' => 50,
            'max_attempts' => 1,
        ]);
    }

    private function question(string $text, array $options, string $answer, int $order): ExamQuestion
    {
        return ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => $text,
            'type' => 'mcq',
            'options' => $options,
            'answer' => $answer,
            'points' => 1,
            'order' => $order,
        ]);
    }

    private function attempt(): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'start_time' => now(),
            'submitted' => false,
            'answers' => [],
        ]);
    }

    private function paper(): ExamPaperService
    {
        return app(ExamPaperService::class);
    }

    public function test_the_same_attempt_always_gets_the_same_order(): void
    {
        $this->exam->update(['shuffle_questions' => true]);

        foreach (range(1, 8) as $i) {
            $this->question("Question {$i}", ['A', 'B'], 'A', $i);
        }

        $attempt = $this->attempt();

        $first = $this->paper()->questionsFor($this->exam->fresh(), $attempt)->pluck('id')->all();

        // Taking an exam is a refreshable GET; the paper must not move.
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(
                $first,
                $this->paper()->questionsFor($this->exam->fresh(), $attempt)->pluck('id')->all()
            );
        }
    }

    public function test_shuffling_keeps_every_question(): void
    {
        $this->exam->update(['shuffle_questions' => true]);

        foreach (range(1, 8) as $i) {
            $this->question("Question {$i}", ['A', 'B'], 'A', $i);
        }

        $shuffled = $this->paper()->questionsFor($this->exam->fresh(), $this->attempt());

        $this->assertCount(8, $shuffled);
        $this->assertCount(8, $shuffled->pluck('id')->unique());
    }

    public function test_order_is_untouched_when_shuffling_is_off(): void
    {
        foreach (range(1, 5) as $i) {
            $this->question("Question {$i}", ['A', 'B'], 'A', $i);
        }

        $this->assertSame(
            ['Question 1', 'Question 2', 'Question 3', 'Question 4', 'Question 5'],
            $this->paper()->questionsFor($this->exam->fresh(), $this->attempt())->pluck('question')->all()
        );
    }

    public function test_true_false_options_are_never_reordered(): void
    {
        $this->exam->update(['shuffle_options' => true]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'The Nile flows north.',
            'type' => 'true_false',
            'options' => ['True', 'False'],
            'answer' => 'True',
            'points' => 1,
            'order' => 1,
        ]);

        $rendered = $this->paper()->questionsFor($this->exam->fresh(), $this->attempt())->first();

        $this->assertSame(['True', 'False'], $rendered->options);
    }

    public function test_shuffling_options_does_not_change_the_correct_answer(): void
    {
        $this->exam->update(['shuffle_options' => true]);

        $this->question('Capital of France?', ['London', 'Paris', 'Rome', 'Madrid'], 'Paris', 1);

        $rendered = $this->paper()->questionsFor($this->exam->fresh(), $this->attempt())->first();

        $this->assertContains('Paris', $rendered->options);
        $this->assertSame('Paris', $this->paper()->correctAnswer($rendered));
    }

    public function test_grading_reads_answer_text_so_a_shuffled_paper_still_scores(): void
    {
        $this->exam->update(['shuffle_options' => true]);

        $q = $this->question('Capital of France?', ['London', 'Paris', 'Rome'], 'Paris', 1);

        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->post(route('student.exams.submit', [$this->exam, $attempt]), [
                'q' => [$q->id => 'Paris'],
            ])->assertRedirect();

        $attempt->refresh();

        $this->assertEquals(100, $attempt->percentage);
        $this->assertTrue($attempt->passed);
        $this->assertSame('Paris', $attempt->answers[0]['given']);
    }

    public function test_questions_that_stored_the_answer_as_an_index_still_grade(): void
    {
        // Hand-written questions predating the bank stored the option position.
        $q = $this->question('Capital of France?', ['London', 'Paris', 'Rome'], '1', 1);

        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->post(route('student.exams.submit', [$this->exam, $attempt]), [
                'q' => [$q->id => 'Paris'],
            ])->assertRedirect();

        $this->assertEquals(100, $attempt->fresh()->percentage);
    }

    public function test_numeric_options_are_not_mistaken_for_an_index(): void
    {
        $q = $this->question('Which year?', ['1999', '2024', '2030'], '2024', 1);

        $this->assertSame('2024', $this->paper()->correctAnswer($q));
    }

    public function test_written_answers_ignore_case_and_padding(): void
    {
        $q = ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'Capital of France?',
            'type' => 'short_answer',
            'options' => null,
            'answer' => 'Paris',
            'points' => 1,
            'order' => 1,
        ]);

        $this->assertTrue($this->paper()->isCorrect($q, '  paris '));
        $this->assertFalse($this->paper()->isCorrect($q, 'Lyon'));
        $this->assertFalse($this->paper()->isCorrect($q, null));
    }

    public function test_a_student_cannot_start_before_the_exam_opens(): void
    {
        $this->exam->update(['start_time' => now()->addDay()]);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertSessionHasErrors('exam');

        $this->assertSame(0, ExamAttempt::where('exam_id', $this->exam->id)->count());
    }

    public function test_a_student_cannot_start_after_the_exam_closes(): void
    {
        $this->exam->update(['end_time' => now()->subHour()]);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertSessionHasErrors('exam');

        $this->assertSame(0, ExamAttempt::where('exam_id', $this->exam->id)->count());
    }

    public function test_a_student_can_start_inside_the_window(): void
    {
        $this->exam->update([
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        $this->question('Capital of France?', ['London', 'Paris'], 'Paris', 1);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $this->exam))
            ->assertRedirect();

        $this->assertSame(1, ExamAttempt::where('exam_id', $this->exam->id)->count());
    }

    public function test_the_explanation_is_not_visible_while_sitting_the_exam(): void
    {
        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question' => 'Capital of France?',
            'type' => 'mcq',
            'options' => ['London', 'Paris'],
            'answer' => 'Paris',
            'explanation' => 'Paris has been the capital since 508.',
            'points' => 1,
            'order' => 1,
        ]);

        $attempt = $this->attempt();

        $this->actingAs($this->student)
            ->get(route('student.exams.take', [$this->exam, $attempt]))
            ->assertOk()
            // Assert the page really rendered, so the check below cannot pass
            // just because the request failed.
            ->assertSee('Capital of France?')
            ->assertDontSee('Paris has been the capital since 508.');
    }
}
