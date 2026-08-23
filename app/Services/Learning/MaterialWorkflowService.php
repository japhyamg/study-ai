<?php

namespace App\Services\Learning;

use App\Models\Material;
use App\Models\SubmissionNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The review workflow for AI-generated material.
 *
 * Every transition that a human triggers goes through here so that the state
 * change and its audit note are written together — a rejection whose reason
 * went missing is worse than no rejection at all.
 *
 * Illegal transitions raise a ValidationException rather than silently doing
 * nothing, so a stale tab that posts "approve" twice gets a real message.
 */
class MaterialWorkflowService
{
    /** Teacher sends a material to an admin for review. */
    public function submit(Material $material, User $teacher, ?string $note = null): Material
    {
        $this->guardHasContent($material);

        return $this->apply(
            $material,
            Material::STATE_SUBMITTED,
            $teacher,
            SubmissionNote::TYPE_SUBMISSION,
            $note ?: 'Submitted for review.'
        );
    }

    /** Admin opens a submission — signals to the teacher that it is being read. */
    public function beginReview(Material $material, User $admin): Material
    {
        if ($material->workflow_state !== Material::STATE_SUBMITTED) {
            return $material;
        }

        return $this->apply($material, Material::STATE_UNDER_REVIEW, $admin);
    }

    /**
     * Approve a material.
     *
     * In a small school the teacher who made the material is also the person
     * who signs it off, so a material sitting in a pre-submission state is
     * submitted on their behalf first. That keeps the audit trail honest — the
     * submission is still recorded — without forcing a pointless two-click
     * dance on a one-teacher department.
     */
    public function approve(Material $material, User $admin, ?string $note = null): Material
    {
        if (in_array($material->workflow_state, [
            Material::STATE_DRAFT,
            Material::STATE_AI_COMPLETED,
            Material::STATE_CHANGES_REQUESTED,
        ], true)) {
            $this->submit($material, $admin, 'Submitted and approved by '.$admin->name.'.');
            $material->refresh();
        }

        return $this->apply(
            $material,
            Material::STATE_APPROVED,
            $admin,
            SubmissionNote::TYPE_APPROVAL,
            $note
        );
    }

    /**
     * Approve and publish in one step — the common case, since an admin
     * approving something almost always wants students to see it.
     */
    public function approveAndPublish(Material $material, User $admin, ?string $note = null): Material
    {
        $this->approve($material, $admin, $note);

        return $this->publish($material->refresh(), $admin);
    }

    /** Send back with required changes; the teacher can revise and resubmit. */
    public function requestChanges(Material $material, User $admin, string $note): Material
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => 'Say what needs to change — the teacher only sees this note.',
            ]);
        }

        return $this->apply(
            $material,
            Material::STATE_CHANGES_REQUESTED,
            $admin,
            SubmissionNote::TYPE_CHANGE_REQUEST,
            $note,
            ['review_notes' => $note]
        );
    }

    public function reject(Material $material, User $admin, string $reason): Material
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection needs a reason.',
            ]);
        }

        return $this->apply(
            $material,
            Material::STATE_REJECTED,
            $admin,
            SubmissionNote::TYPE_REJECTION,
            $reason,
            ['review_notes' => $reason]
        );
    }

    /** Make an approved material visible to students. */
    public function publish(Material $material, User $actor): Material
    {
        return $this->apply($material, Material::STATE_PUBLISHED, $actor);
    }

    /** Pull a published material back for revision. */
    public function unpublish(Material $material, User $actor, ?string $note = null): Material
    {
        return $this->apply(
            $material,
            Material::STATE_APPROVED,
            $actor,
            $note ? SubmissionNote::TYPE_ADMIN_NOTE : null,
            $note
        );
    }

    /** A free-text note that does not move the material. */
    public function addNote(Material $material, User $author, string $content): SubmissionNote
    {
        return SubmissionNote::create([
            'material_id' => $material->id,
            'user_id' => $author->id,
            'note_type' => SubmissionNote::TYPE_ADMIN_NOTE,
            'content' => $content,
        ]);
    }

    // ── internals ──

    /**
     * Perform a transition and record its note atomically.
     *
     * @param  array<string, mixed>  $extraAttributes
     */
    private function apply(
        Material $material,
        string $state,
        User $actor,
        ?string $noteType = null,
        ?string $note = null,
        array $extraAttributes = []
    ): Material {
        if (! $material->canTransitionTo($state) && $material->workflow_state !== $state) {
            throw ValidationException::withMessages([
                'workflow_state' => sprintf(
                    'This material is %s and cannot move to %s.',
                    strtolower($material->stateLabel()),
                    strtolower(Material::STATE_LABELS[$state] ?? $state)
                ),
            ]);
        }

        return DB::transaction(function () use ($material, $state, $actor, $noteType, $note, $extraAttributes) {
            $material->transitionTo($state, $actor);

            if ($extraAttributes !== []) {
                $material->update($extraAttributes);
            }

            if ($noteType && $note !== null && trim($note) !== '') {
                SubmissionNote::create([
                    'material_id' => $material->id,
                    'user_id' => $actor->id,
                    'note_type' => $noteType,
                    'content' => $note,
                ]);
            }

            return $material->refresh();
        });
    }

    /**
     * Refuse to submit an empty material — otherwise an admin is asked to
     * review a title and nothing else.
     */
    private function guardHasContent(Material $material): void
    {
        if (! $material->hasGeneratedContent()) {
            throw ValidationException::withMessages([
                'workflow_state' => 'Generate study content before submitting this material for review.',
            ]);
        }
    }
}
