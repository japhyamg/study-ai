<?php

namespace Tests\Feature\Learning;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\Material;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use App\Services\Learning\MaterialWorkflowService;
use App\Services\Learning\QuestionBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The subject question bank.
 *
 * Approved quiz questions accumulate into their subject's bank so a teacher
 * can reuse them when building an exam later. Two rules carry the design:
 *
 *  - banking happens on approval, never on generation, so unreviewed AI
 *    output cannot quietly become a pool people trust;
 *  - a bank belongs to a subject, so only teachers assigned to that subject
 *    can see or edit it.
 */
class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Subject $maths;

    private Subject $chemistry;

    private User $mathsTeacher;

    private User $chemistryTeacher;

    private User $admin;

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
        $this->chemistryTeacher = $this->user('chem@test.test', SchoolMember::ROLE_TEACHER);
        $this->admin = $this->user('admin@test.test', SchoolMember::ROLE_ADMIN);

        $this->assign($this->mathsTeacher, $this->maths);
        $this->assign($this->chemistryTeacher, $this->chemistry);
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

    private function assign(User $teacher, Subject $subject): void
    {
        ClassSubjectAssignment::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $this->class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /** A material with a generated quiz, ready for review. */
    private function material(Subject $subject, User $creator, string $title = 'Quadratic Equations'): Material
    {
        $material = Material::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $this->class->id,
            'subject_id' => $subject->id,
            'created_by' => $creator->id,
            'title' => $title,
            'type' => 'note',
            'content' => 'Lesson content.',
            'workflow_state' => Material::STATE_AI_COMPLETED,
        ]);

        $material->questions()->create([
            'question' => 'What is the discriminant of ax^2 + bx + c?',
            'type' => 'multiple-choice',
            'options' => ['b^2 - 4ac', 'b^2 + 4ac', '2a', '-b/2a'],
            'correct_idx' => 0,
            'explanation' => 'It determines the number of real roots.',
            'difficulty' => 3,
        ]);

        $material->questions()->create([
            'question' => 'A quadratic has at most how many real roots?',
            'type' => 'multiple-choice',
            'options' => ['1', '2', '3'],
            'correct_idx' => 1,
            'difficulty' => 1,
        ]);

        return $material->fresh();
    }

    private function approve(Material $material): void
    {
        app(MaterialWorkflowService::class)->approve($material, $this->admin);
    }

    // ───────────────────────── banking on approval ─────────────────────────

    public function test_approval_banks_the_quiz_questions(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);

        $this->assertSame(0, QuestionBank::count());

        $this->approve($material);

        $this->assertSame(2, QuestionBank::where('subject_id', $this->maths->id)->count());
    }

    /** The gate: unreviewed output must not accumulate. */
    public function test_generated_questions_are_not_banked_before_approval(): void
    {
        $this->material($this->maths, $this->mathsTeacher);

        $this->assertSame(0, QuestionBank::count());
    }

    public function test_a_banked_question_keeps_its_answer_as_text(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);

        $this->approve($material);

        $banked = QuestionBank::where('question', 'like', '%discriminant%')->first();

        // Stored as text, not an index — options can be edited or shuffled
        // after banking, and an index would then point at the wrong answer.
        $this->assertSame('b^2 - 4ac', $banked->answer);
    }

    /** The generator says "multiple-choice"; the bank says "mcq". */
    public function test_the_question_type_is_translated_for_the_bank(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);

        $this->approve($material);

        $this->assertSame(QuestionBank::TYPE_MCQ, QuestionBank::first()->type);
    }

    public function test_a_banked_question_records_the_topic_it_came_from(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);

        $this->approve($material);

        $this->assertSame('Quadratic Equations', QuestionBank::first()->topic);
    }

    /** Re-approving must top the bank up, not duplicate it. */
    public function test_approving_twice_does_not_duplicate_the_bank(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);

        $this->approve($material);
        $this->assertSame(2, QuestionBank::count());

        // Unpublish → revise → approve again.
        $material->refresh()->transitionTo(Material::STATE_PUBLISHED);
        app(MaterialWorkflowService::class)->unpublish($material->refresh(), $this->admin);
        app(QuestionBankService::class)->bankFor($material->refresh());

        $this->assertSame(2, QuestionBank::count());
    }

    public function test_a_question_added_after_approval_is_banked_on_the_next_pass(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);
        $this->approve($material);

        $material->questions()->create([
            'question' => 'Late addition?',
            'type' => 'multiple-choice',
            'options' => ['yes', 'no'],
            'correct_idx' => 0,
        ]);

        $added = app(QuestionBankService::class)->bankFor($material->fresh());

        $this->assertSame(1, $added);
        $this->assertSame(3, QuestionBank::count());
    }

    /** Without a subject there is no bank to file under. */
    public function test_a_material_with_no_subject_banks_nothing(): void
    {
        $material = Material::create([
            'school_id' => $this->school->id,
            'created_by' => $this->mathsTeacher->id,
            'title' => 'Unfiled',
            'type' => 'note',
            'content' => 'x',
            'workflow_state' => Material::STATE_AI_COMPLETED,
        ]);

        $material->questions()->create([
            'question' => 'Q', 'options' => ['a', 'b'], 'correct_idx' => 0,
        ]);

        $this->approve($material->fresh());

        $this->assertSame(0, QuestionBank::count());
    }

    // ───────────────────────── who can see a bank ─────────────────────────

    public function test_the_index_lists_only_the_teachers_own_subjects(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.question-bank.index'))
            ->assertOk()
            ->assertSee('Maths')
            ->assertDontSee('Chemistry');
    }

    public function test_the_index_counts_the_questions_in_each_subject(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.question-bank.index'))
            ->assertOk()
            // The count sits in its own span, so assert the pieces.
            ->assertSee('>2<', false)
            ->assertSee('questions');
    }

    public function test_a_teacher_sees_their_own_subjects_questions(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.question-bank.show', $this->maths))
            ->assertOk()
            ->assertSee('discriminant');
    }

    /** The access rule that makes a bank a subject's own. */
    public function test_a_teacher_cannot_open_another_subjects_bank(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));

        $this->actingAs($this->chemistryTeacher)
            ->get(route('teacher.question-bank.show', $this->maths))
            ->assertForbidden();
    }

    public function test_an_admin_can_open_any_subjects_bank(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));

        $this->actingAs($this->admin)
            ->get(route('teacher.question-bank.show', $this->maths))
            ->assertOk()
            ->assertSee('discriminant');
    }

    public function test_questions_are_grouped_by_the_guide_they_came_from(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher, 'Quadratic Equations'));
        $this->approve($this->material($this->maths, $this->mathsTeacher, 'Trigonometry'));

        $this->actingAs($this->mathsTeacher)
            ->get(route('teacher.question-bank.show', $this->maths))
            ->assertOk()
            ->assertSee('Quadratic Equations')
            ->assertSee('Trigonometry');
    }

    // ───────────────────────── editing ─────────────────────────

    public function test_a_teacher_can_correct_a_choice_question(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));
        $banked = QuestionBank::where('question', 'like', '%discriminant%')->first();

        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.question-bank.update', $banked), [
                'question' => 'Corrected question?',
                'type' => 'mcq',
                'options' => ['wrong', 'right', 'also wrong'],
                'correct_idx' => 1,
                'difficulty' => 4,
            ]);

        $fresh = $banked->fresh();

        $this->assertSame('Corrected question?', $fresh->question);
        // Stored as text, resolved from the index that was submitted.
        $this->assertSame('right', $fresh->answer);
        $this->assertSame(4, $fresh->difficulty);
    }

    /** The key must never point past the options it was submitted with. */
    public function test_the_correct_option_must_exist(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));
        $banked = QuestionBank::first();

        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.question-bank.update', $banked), [
                'question' => 'Q',
                'type' => 'mcq',
                'options' => ['a', 'b'],
                'correct_idx' => 5,
            ])
            ->assertSessionHasErrors('correct_idx');
    }

    public function test_switching_to_a_written_type_clears_the_options(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));
        $banked = QuestionBank::first();

        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.question-bank.update', $banked), [
                'question' => 'Explain the discriminant.',
                'type' => 'short_answer',
                'answer' => 'It determines the number of real roots.',
            ]);

        $fresh = $banked->fresh();

        $this->assertNull($fresh->options);
        $this->assertSame('It determines the number of real roots.', $fresh->answer);
    }

    public function test_a_written_question_needs_an_answer(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));
        $banked = QuestionBank::first();

        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.question-bank.update', $banked), [
                'question' => 'Q',
                'type' => 'short_answer',
                'answer' => '   ',
            ])
            ->assertSessionHasErrors('answer');
    }

    public function test_true_false_is_normalised_to_two_options(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));
        $banked = QuestionBank::first();

        $this->actingAs($this->mathsTeacher)
            ->put(route('teacher.question-bank.update', $banked), [
                'question' => 'A quadratic has two roots.',
                'type' => 'true_false',
                'options' => ['anything', 'at', 'all'],
                'correct_idx' => 0,
            ]);

        $fresh = $banked->fresh();

        $this->assertSame(['True', 'False'], $fresh->options);
        $this->assertSame('True', $fresh->answer);
    }

    public function test_a_teacher_cannot_edit_another_subjects_question(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));
        $banked = QuestionBank::first();

        $this->actingAs($this->chemistryTeacher)
            ->put(route('teacher.question-bank.update', $banked), [
                'question' => 'Hijacked',
                'type' => 'mcq',
                'options' => ['a', 'b'],
                'correct_idx' => 0,
            ])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $banked->fresh()->question);
    }

    public function test_a_teacher_cannot_delete_another_subjects_question(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));

        $banked = QuestionBank::first();

        $this->actingAs($this->chemistryTeacher)
            ->delete(route('teacher.question-bank.destroy', $banked))
            ->assertForbidden();

        $this->assertDatabaseHas('question_bank', ['id' => $banked->id]);
    }

    public function test_a_teacher_can_delete_from_their_own_subject(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher));

        $banked = QuestionBank::first();

        $this->actingAs($this->mathsTeacher)
            ->delete(route('teacher.question-bank.destroy', $banked));

        $this->assertDatabaseMissing('question_bank', ['id' => $banked->id]);
    }

    public function test_a_teacher_cannot_add_to_a_subject_they_do_not_teach(): void
    {
        $this->actingAs($this->mathsTeacher)
            ->post(route('teacher.question-bank.store'), [
                'question' => 'Sneaky',
                'type' => 'mcq',
                'answer' => 'x',
                'subject_id' => $this->chemistry->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, QuestionBank::count());
    }

    // ───────────────────────── accumulating over time ─────────────────────────

    /** The point of the feature: many topics, one subject pool. */
    public function test_questions_from_different_topics_collect_in_one_subject_bank(): void
    {
        $this->approve($this->material($this->maths, $this->mathsTeacher, 'Quadratic Equations'));
        $this->approve($this->material($this->maths, $this->mathsTeacher, 'Trigonometry'));

        $this->assertSame(4, QuestionBank::where('subject_id', $this->maths->id)->count());
        $this->assertEqualsCanonicalizing(
            ['Quadratic Equations', 'Trigonometry'],
            QuestionBank::distinct()->pluck('topic')->all()
        );
    }

    /** The bank must outlive the study guide a teacher tidies away. */
    public function test_banked_questions_survive_the_material_being_deleted(): void
    {
        $material = $this->material($this->maths, $this->mathsTeacher);
        $this->approve($material);

        $material->refresh()->delete();

        $this->assertSame(2, QuestionBank::count());
        $this->assertSame('Quadratic Equations', QuestionBank::first()->topic);
        $this->assertNull(QuestionBank::first()->material_id);
    }
}
