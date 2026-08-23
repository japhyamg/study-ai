<?php

namespace Tests\Feature\Learning;

use App\Models\Flashcard;
use App\Models\Material;
use App\Models\Question;
use App\Models\School;
use App\Models\StudyGuide;
use App\Models\User;
use App\Services\AiContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * What a model returns is never quite the shape you asked for. These cover the
 * normalisation layer — the difference between dropping one bad question and
 * storing a quiz whose "correct" answer points past the end of its options.
 */
class AiContentGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'subdomain' => 'test',
            'status' => School::STATUS_ACTIVE,
        ]);

        app()->instance('tenant', $school);

        $teacher = User::create([
            'school_id' => $school->id,
            'name' => 'Teacher',
            'email' => 'teacher@test.test',
            'password' => Hash::make('password'),
        ]);

        $this->material = Material::create([
            'school_id' => $school->id,
            'title' => 'Photosynthesis',
            'type' => 'note',
            'content' => 'Plants convert light energy into chemical energy.',
            'status' => Material::STATUS_READY,
            'workflow_state' => Material::STATE_AI_COMPLETED,
            'created_by' => $teacher->id,
        ]);
    }

    private function service(): AiContentService
    {
        return app(AiContentService::class);
    }

    // ───────────────────────── questions ─────────────────────────

    public function test_saves_a_well_formed_question(): void
    {
        $saved = $this->service()->saveQuestions($this->material, [[
            'question' => 'What do plants convert light into?',
            'options' => ['Sugar', 'Water', 'Nitrogen', 'Salt'],
            'correctIdx' => 0,
            'explanation' => 'Photosynthesis produces glucose.',
            'difficulty' => 2,
        ]]);

        $this->assertSame(1, $saved);

        $question = Question::first();
        $this->assertSame(0, $question->correct_idx);
        $this->assertCount(4, $question->options);
    }

    public function test_accepts_options_given_as_objects_with_is_correct(): void
    {
        // The shape schoolsync's prompts produce, rather than a flat array.
        $this->service()->saveQuestions($this->material, [[
            'question_text' => 'Which gas is released?',
            'options' => [
                ['option_text' => 'Carbon dioxide', 'is_correct' => false],
                ['option_text' => 'Oxygen', 'is_correct' => true],
                ['option_text' => 'Hydrogen', 'is_correct' => false],
                ['option_text' => 'Argon', 'is_correct' => false],
            ],
        ]]);

        $question = Question::first();

        $this->assertNotNull($question);
        $this->assertSame(1, $question->correct_idx);
        $this->assertSame('Oxygen', $question->options[1]);
    }

    public function test_drops_a_question_whose_correct_index_is_out_of_range(): void
    {
        // Storing this would give students a quiz with no right answer.
        $saved = $this->service()->saveQuestions($this->material, [[
            'question' => 'Broken',
            'options' => ['a', 'b'],
            'correctIdx' => 7,
        ]]);

        $this->assertSame(0, $saved);
        $this->assertSame(0, Question::count());
    }

    public function test_drops_a_question_with_duplicate_options(): void
    {
        $saved = $this->service()->saveQuestions($this->material, [[
            'question' => 'Which one?',
            'options' => ['Same', 'Same', 'Other', 'Another'],
            'correctIdx' => 0,
        ]]);

        $this->assertSame(0, $saved);
    }

    public function test_keeps_good_questions_and_drops_bad_ones_in_the_same_batch(): void
    {
        $saved = $this->service()->saveQuestions($this->material, [
            ['question' => 'Good', 'options' => ['a', 'b', 'c', 'd'], 'correctIdx' => 1],
            ['question' => 'No options'],
            ['question' => 'Bad index', 'options' => ['a', 'b'], 'correctIdx' => 5],
            ['question' => 'Also good', 'options' => ['w', 'x', 'y', 'z'], 'correctIdx' => 3],
        ]);

        $this->assertSame(2, $saved);
    }

    public function test_accepts_a_response_wrapped_in_a_questions_key(): void
    {
        $saved = $this->service()->saveQuestions($this->material, [
            'questions' => [
                ['question' => 'Wrapped', 'options' => ['a', 'b', 'c', 'd'], 'correctIdx' => 0],
            ],
        ]);

        $this->assertSame(1, $saved);
    }

    public function test_word_difficulties_are_mapped_onto_the_numeric_scale(): void
    {
        $this->service()->saveQuestions($this->material, [
            ['question' => 'Easy one', 'options' => ['a', 'b', 'c', 'd'], 'correctIdx' => 0, 'difficulty' => 'easy'],
            ['question' => 'Hard one', 'options' => ['a', 'b', 'c', 'd'], 'correctIdx' => 0, 'difficulty' => 'hard'],
        ]);

        $this->assertSame(1, Question::where('question', 'Easy one')->value('difficulty'));
        $this->assertSame(3, Question::where('question', 'Hard one')->value('difficulty'));
    }

    public function test_regenerating_replaces_questions_rather_than_appending(): void
    {
        $batch = [['question' => 'First', 'options' => ['a', 'b', 'c', 'd'], 'correctIdx' => 0]];

        $this->service()->saveQuestions($this->material, $batch);
        $this->service()->saveQuestions($this->material, $batch);

        $this->assertSame(1, Question::count(), 'A second run must not duplicate.');
    }

    // ───────────────────────── flashcards ─────────────────────────

    public function test_saves_flashcards_and_seeds_them_for_spaced_repetition(): void
    {
        $saved = $this->service()->saveFlashcards($this->material, [
            ['front' => 'What is photosynthesis?', 'back' => 'Light to chemical energy.', 'tags' => ['biology']],
        ]);

        $this->assertSame(1, $saved);

        $card = Flashcard::first();
        $this->assertSame(2.5, $card->ease_factor, 'SM-2 starts at a neutral ease.');
        $this->assertSame(0, $card->repetitions);
        $this->assertNotNull($card->due_date, 'A new card is due immediately.');
        $this->assertSame(['biology'], $card->tags);
    }

    public function test_accepts_question_and_answer_as_flashcard_field_names(): void
    {
        $saved = $this->service()->saveFlashcards($this->material, [
            ['question' => 'Front text', 'answer' => 'Back text'],
        ]);

        $this->assertSame(1, $saved);
        $this->assertSame('Front text', Flashcard::first()->front);
    }

    public function test_skips_flashcards_missing_a_side(): void
    {
        $saved = $this->service()->saveFlashcards($this->material, [
            ['front' => 'Only a front'],
            ['front' => 'Complete', 'back' => 'Pair'],
            ['back' => 'Only a back'],
        ]);

        $this->assertSame(1, $saved);
    }

    // ───────────────────────── study guide ─────────────────────────

    public function test_saves_a_study_guide_with_sections_and_key_terms(): void
    {
        $this->service()->saveStudyGuide($this->material, [
            'title' => 'Photosynthesis',
            'summary' => 'How plants make food.',
            'sections' => [
                ['heading' => 'Overview', 'body' => 'The basics.'],
                ['heading' => 'Chlorophyll', 'content' => 'The green pigment.'],
            ],
            'keyTerms' => [['term' => 'Chlorophyll', 'definition' => 'A green pigment.']],
        ]);

        $guide = StudyGuide::first();

        $this->assertSame('Photosynthesis', $guide->title);
        $this->assertCount(2, $guide->sections);
        // Both `body` and `content` are accepted and stored under `body`.
        $this->assertSame('The green pigment.', $guide->sections[1]['body']);
        $this->assertCount(1, $guide->normalisedKeyTerms());
        $this->assertStringContainsString('## Overview', $guide->content);
    }

    public function test_sections_without_a_body_are_dropped(): void
    {
        $this->service()->saveStudyGuide($this->material, [
            'title' => 'T',
            'sections' => [
                ['heading' => 'Empty', 'body' => '   '],
                ['heading' => 'Real', 'body' => 'Content here.'],
            ],
        ]);

        $this->assertCount(1, StudyGuide::first()->sections);
    }

    public function test_regenerating_a_guide_replaces_the_existing_one(): void
    {
        $this->service()->saveStudyGuide($this->material, ['title' => 'First', 'sections' => []]);
        $this->service()->saveStudyGuide($this->material, ['title' => 'Second', 'sections' => []]);

        $this->assertSame(1, StudyGuide::count());
        $this->assertSame('Second', StudyGuide::first()->title);
    }
}
