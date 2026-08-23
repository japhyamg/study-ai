<?php

namespace Tests\Feature\Learning;

use App\Models\Flashcard;
use App\Models\Material;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\SubmissionNote;
use App\Models\User;
use App\Services\Learning\MaterialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The review workflow decides what students can see, so the rules that matter
 * are the ones that stop material skipping review, and the audit trail that
 * explains every decision after the fact.
 */
class MaterialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    private User $admin;

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

        $this->teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER);
        $this->admin = $this->user('admin@test.test', SchoolMember::ROLE_ADMIN);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => ucfirst($role),
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

    private function service(): MaterialWorkflowService
    {
        return app(MaterialWorkflowService::class);
    }

    private function material(string $state = Material::STATE_AI_COMPLETED, bool $withContent = true): Material
    {
        $material = Material::create([
            'school_id' => $this->school->id,
            'title' => 'Photosynthesis',
            'type' => 'note',
            'content' => 'Plants convert light into chemical energy.',
            'status' => Material::STATUS_READY,
            'workflow_state' => $state,
            'review_status' => Material::REVIEW_PENDING,
            'published' => false,
            'created_by' => $this->teacher->id,
        ]);

        if ($withContent) {
            Flashcard::create([
                'user_id' => $this->teacher->id,
                'material_id' => $material->id,
                'front' => 'What is photosynthesis?',
                'back' => 'Converting light into chemical energy.',
                'due_date' => now(),
            ]);
        }

        return $material->fresh();
    }

    // ───────────────────────── state machine ─────────────────────────

    public function test_a_draft_cannot_jump_straight_to_published(): void
    {
        $material = $this->material(Material::STATE_DRAFT);

        $this->assertFalse($material->canTransitionTo(Material::STATE_PUBLISHED));
        $this->assertFalse($material->transitionTo(Material::STATE_PUBLISHED));
        $this->assertSame(Material::STATE_DRAFT, $material->fresh()->workflow_state);
    }

    public function test_transitioning_to_the_current_state_is_a_no_op(): void
    {
        $material = $this->material(Material::STATE_DRAFT);

        $this->assertTrue($material->transitionTo(Material::STATE_DRAFT));
    }

    public function test_legacy_columns_stay_in_sync_with_the_workflow_state(): void
    {
        $material = $this->material(Material::STATE_AI_COMPLETED);

        $this->service()->approveAndPublish($material, $this->admin);
        $material->refresh();

        $this->assertSame(Material::STATE_PUBLISHED, $material->workflow_state);
        $this->assertTrue($material->published, 'The legacy published flag must follow.');
        $this->assertSame(Material::REVIEW_APPROVED, $material->review_status);
        $this->assertNotNull($material->published_at);
    }

    // ───────────────────────── submission ─────────────────────────

    public function test_submitting_records_a_note_and_a_timestamp(): void
    {
        $material = $this->material();

        $this->service()->submit($material, $this->teacher, 'Ready for you.');
        $material->refresh();

        $this->assertSame(Material::STATE_SUBMITTED, $material->workflow_state);
        $this->assertNotNull($material->submitted_at);
        $this->assertSame('Ready for you.', $material->notes()->first()->content);
        $this->assertSame(SubmissionNote::TYPE_SUBMISSION, $material->notes()->first()->note_type);
    }

    public function test_material_with_no_generated_content_cannot_be_submitted(): void
    {
        $material = $this->material(Material::STATE_DRAFT, withContent: false);

        $this->expectException(ValidationException::class);
        $this->service()->submit($material, $this->teacher);
    }

    // ───────────────────────── review decisions ─────────────────────────

    public function test_requesting_changes_stores_the_reason_where_the_teacher_sees_it(): void
    {
        $material = $this->material();
        $this->service()->submit($material, $this->teacher);

        $this->service()->requestChanges($material->refresh(), $this->admin, 'Question 3 has two correct answers.');
        $material->refresh();

        $this->assertSame(Material::STATE_CHANGES_REQUESTED, $material->workflow_state);
        $this->assertSame('Question 3 has two correct answers.', $material->review_notes);
        $this->assertSame($this->admin->id, $material->reviewed_by);
    }

    public function test_requesting_changes_requires_a_note(): void
    {
        $material = $this->material();
        $this->service()->submit($material, $this->teacher);

        $this->expectException(ValidationException::class);
        $this->service()->requestChanges($material->refresh(), $this->admin, '   ');
    }

    /**
     * The bug this replaces: reject() wrote review_notes to a column that did
     * not exist, so every rejection reason was silently discarded.
     */
    public function test_rejection_reason_is_persisted(): void
    {
        $material = $this->material();
        $this->service()->submit($material, $this->teacher);

        $this->service()->reject($material->refresh(), $this->admin, 'Off-syllabus.');
        $material->refresh();

        $this->assertSame(Material::STATE_REJECTED, $material->workflow_state);
        $this->assertSame('Off-syllabus.', $material->review_notes);
        $this->assertSame('Off-syllabus.', $material->notes()->first()->content);
    }

    public function test_a_teacher_can_revise_and_resubmit_after_changes_are_requested(): void
    {
        $material = $this->material();
        $this->service()->submit($material, $this->teacher);
        $this->service()->requestChanges($material->refresh(), $this->admin, 'Fix question 3.');

        $this->service()->submit($material->refresh(), $this->teacher, 'Fixed.');

        $this->assertSame(Material::STATE_SUBMITTED, $material->fresh()->workflow_state);
        $this->assertCount(3, $material->fresh()->notes, 'Every step stays in the trail.');
    }

    public function test_approving_from_a_pre_submission_state_still_records_the_submission(): void
    {
        // Small schools: the teacher is also the approver.
        $material = $this->material(Material::STATE_AI_COMPLETED);

        $this->service()->approve($material, $this->admin);
        $material->refresh();

        $this->assertSame(Material::STATE_APPROVED, $material->workflow_state);
        $this->assertNotNull($material->submitted_at, 'The submission step must not be skipped in the record.');
    }

    public function test_approved_material_is_not_visible_until_published(): void
    {
        $material = $this->material();
        $this->service()->submit($material, $this->teacher);
        $this->service()->approve($material->refresh(), $this->admin);

        $this->assertSame(0, Material::published()->count());

        $this->service()->publish($material->refresh(), $this->admin);

        $this->assertSame(1, Material::published()->count());
    }

    public function test_unpublishing_hides_material_from_students_again(): void
    {
        $material = $this->material();
        $this->service()->approveAndPublish($material, $this->admin);

        $this->service()->unpublish($material->refresh(), $this->admin, 'Contains an error.');

        $this->assertSame(Material::STATE_APPROVED, $material->fresh()->workflow_state);
        $this->assertFalse($material->fresh()->published);
        $this->assertSame(0, Material::published()->count());
    }

    public function test_approving_an_already_published_material_is_rejected_with_a_message(): void
    {
        $material = $this->material();
        $this->service()->approveAndPublish($material, $this->admin);

        $this->expectException(ValidationException::class);
        $this->service()->requestChanges($material->refresh(), $this->admin, 'x');
        $this->service()->reject($material->refresh(), $this->admin, 'too late');
    }

    // ───────────────────────── scopes ─────────────────────────

    public function test_review_queue_scope_only_returns_material_waiting_on_an_admin(): void
    {
        $this->material(Material::STATE_DRAFT);
        $this->material(Material::STATE_AI_FAILED);
        $submitted = $this->material();
        $this->service()->submit($submitted, $this->teacher);

        $this->assertSame(1, Material::awaitingReview()->count());
    }
}
