<?php

namespace Tests\Feature\Learning;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Material;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A teacher correcting generated content before sending it for review.
 *
 * Generated material is a first draft. The model will occasionally write a
 * question with no right answer, or miss a card the teacher wants, so the
 * review step has to allow correcting and adding — not just approving what
 * arrived.
 */
class GeneratedContentEditingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $owner;

    private User $otherTeacher;

    private Material $material;

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

        $class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $this->owner = $this->user('owner@test.test', SchoolMember::ROLE_TEACHER);
        $this->otherTeacher = $this->user('other@test.test', SchoolMember::ROLE_TEACHER);

        $this->material = Material::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $class->id,
            'subject_id' => $subject->id,
            'created_by' => $this->owner->id,
            'title' => 'Algebra',
            'content' => 'Lesson content for the guide.',
            'workflow_state' => Material::STATE_AI_COMPLETED,
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

    /** @param array<string, mixed> $overrides */
    private function questionPayload(array $overrides = []): array
    {
        return array_merge([
            'question' => 'What is 2 + 2?',
            'type' => 'multiple-choice',
            'options' => ['3', '4', '5'],
            'correct_idx' => 1,
            'explanation' => 'Two and two make four.',
            'difficulty' => 2,
        ], $overrides);
    }

    // ───────────────────────── flashcards ─────────────────────────

    public function test_a_teacher_can_add_a_flashcard(): void
    {
        $this->actingAs($this->owner)
            ->post(route('teacher.flashcards.store', $this->material), [
                'front' => 'What is a variable?',
                'back' => 'A symbol standing for an unknown value.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('flashcards', [
            'material_id' => $this->material->id,
            'front' => 'What is a variable?',
        ]);
    }

    /** A hand-written card must enter the same SM-2 schedule as a generated one. */
    public function test_an_added_flashcard_starts_due_with_a_neutral_ease(): void
    {
        $this->actingAs($this->owner)->post(route('teacher.flashcards.store', $this->material), [
            'front' => 'Front', 'back' => 'Back',
        ]);

        $card = $this->material->flashcards()->first();

        $this->assertSame(2.5, (float) $card->ease_factor);
        $this->assertSame(0, $card->interval);
        $this->assertSame(0, $card->repetitions);
        $this->assertNotNull($card->due_date);
    }

    public function test_a_teacher_can_correct_a_flashcard(): void
    {
        $card = $this->material->flashcards()->create([
            'user_id' => $this->owner->id, 'front' => 'Wrong', 'back' => 'Also wrong',
        ]);

        $this->actingAs($this->owner)
            ->put(route('teacher.flashcards.update', $card), [
                'front' => 'Corrected front', 'back' => 'Corrected back',
            ]);

        $this->assertSame('Corrected front', $card->fresh()->front);
    }

    public function test_a_teacher_can_delete_a_flashcard(): void
    {
        $card = $this->material->flashcards()->create([
            'user_id' => $this->owner->id, 'front' => 'F', 'back' => 'B',
        ]);

        $this->actingAs($this->owner)->delete(route('teacher.flashcards.destroy', $card));

        $this->assertDatabaseMissing('flashcards', ['id' => $card->id]);
    }

    public function test_another_teacher_cannot_add_to_someone_elses_material(): void
    {
        $this->actingAs($this->otherTeacher)
            ->post(route('teacher.flashcards.store', $this->material), [
                'front' => 'Sneaky', 'back' => 'Card',
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->material->flashcards()->count());
    }

    public function test_a_flashcard_needs_both_sides(): void
    {
        $this->actingAs($this->owner)
            ->post(route('teacher.flashcards.store', $this->material), ['front' => 'Only a front'])
            ->assertSessionHasErrors('back');
    }

    // ───────────────────────── questions ─────────────────────────

    public function test_a_teacher_can_add_a_question(): void
    {
        $this->actingAs($this->owner)
            ->post(route('teacher.questions.store', $this->material), $this->questionPayload())
            ->assertRedirect();

        $question = $this->material->questions()->first();

        $this->assertSame('What is 2 + 2?', $question->question);
        $this->assertSame(['3', '4', '5'], $question->options);
        $this->assertSame(1, $question->correct_idx);
        $this->assertSame(2, $question->difficulty);
    }

    /**
     * The defect this guards: an index past the end of the options list stores
     * cleanly and then reads as "no correct answer" in the quiz.
     */
    public function test_the_correct_answer_must_be_one_of_the_options(): void
    {
        $this->actingAs($this->owner)
            ->post(route('teacher.questions.store', $this->material), $this->questionPayload([
                'options' => ['3', '4'],
                'correct_idx' => 7,
            ]))
            ->assertSessionHasErrors('correct_idx');

        $this->assertSame(0, $this->material->questions()->count());
    }

    public function test_a_question_needs_at_least_one_option(): void
    {
        $this->actingAs($this->owner)
            ->post(route('teacher.questions.store', $this->material), $this->questionPayload([
                'options' => [],
            ]))
            ->assertSessionHasErrors('options');
    }

    /** A written answer has nothing to choose between, so the index is always 0. */
    public function test_a_short_answer_question_stores_its_model_answer(): void
    {
        $this->actingAs($this->owner)
            ->post(route('teacher.questions.store', $this->material), $this->questionPayload([
                'type' => 'short-answer',
                'options' => ['Four'],
                'correct_idx' => 3,
            ]))
            ->assertRedirect();

        $question = $this->material->questions()->first();

        $this->assertSame('short-answer', $question->type);
        $this->assertSame(0, $question->correct_idx);
        $this->assertSame(['Four'], $question->options);
    }

    public function test_a_teacher_can_correct_a_question(): void
    {
        $question = $this->material->questions()->create([
            'question' => 'Old?', 'options' => ['a', 'b'], 'correct_idx' => 0,
        ]);

        $this->actingAs($this->owner)
            ->put(route('teacher.questions.update', $question), $this->questionPayload([
                'question' => 'Corrected?',
                'options' => ['x', 'y'],
                'correct_idx' => 1,
            ]));

        $fresh = $question->fresh();

        $this->assertSame('Corrected?', $fresh->question);
        $this->assertSame(1, $fresh->correct_idx);
        $this->assertSame(['x', 'y'], $fresh->options);
    }

    public function test_editing_cannot_point_the_key_past_the_options(): void
    {
        $question = $this->material->questions()->create([
            'question' => 'Q', 'options' => ['a', 'b', 'c'], 'correct_idx' => 2,
        ]);

        $this->actingAs($this->owner)
            ->put(route('teacher.questions.update', $question), $this->questionPayload([
                'options' => ['a', 'b'],
                'correct_idx' => 2,
            ]))
            ->assertSessionHasErrors('correct_idx');

        $this->assertSame(2, $question->fresh()->correct_idx);
    }

    public function test_a_teacher_can_delete_a_question(): void
    {
        $question = $this->material->questions()->create([
            'question' => 'Q', 'options' => ['a', 'b'], 'correct_idx' => 0,
        ]);

        $this->actingAs($this->owner)->delete(route('teacher.questions.destroy', $question));

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    public function test_another_teacher_cannot_edit_someone_elses_question(): void
    {
        $question = $this->material->questions()->create([
            'question' => 'Q', 'options' => ['a', 'b'], 'correct_idx' => 0,
        ]);

        $this->actingAs($this->otherTeacher)
            ->put(route('teacher.questions.update', $question), $this->questionPayload())
            ->assertForbidden();

        $this->assertSame('Q', $question->fresh()->question);
    }

    // ───────────────────────── the controls are reachable ─────────────────────────

    public function test_the_owner_sees_the_add_controls(): void
    {
        $this->actingAs($this->owner)
            ->get(route('learning.materials.show', $this->material))
            ->assertOk()
            ->assertSee(route('teacher.flashcards.store', $this->material), false)
            ->assertSee(route('teacher.questions.store', $this->material), false);
    }
}
