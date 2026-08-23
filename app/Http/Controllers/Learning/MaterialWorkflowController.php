<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\Learning\MaterialWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The review conversation between a teacher and an admin.
 *
 * Teacher actions (submit) and admin actions (approve, request changes,
 * reject, publish) are separated by policy, not by route prefix, so the same
 * material page can render whichever controls the viewer is entitled to.
 */
class MaterialWorkflowController extends Controller
{
    public function __construct(private MaterialWorkflowService $workflow) {}

    /** Admin queue of everything waiting on review. */
    public function queue(Request $request): View
    {
        $this->authorize('viewAny', Material::class);

        $school = $request->user()->currentSchool();

        $materials = Material::with(['creator', 'subject', 'classArm.classLevel'])
            ->withCount(['flashcards', 'questions'])
            ->where('school_id', $school?->id)
            ->awaitingReview()
            ->oldest('submitted_at')
            ->paginate(20);

        $counts = [
            'awaiting' => Material::where('school_id', $school?->id)->awaitingReview()->count(),
            'published' => Material::where('school_id', $school?->id)->published()->count(),
            'changes' => Material::where('school_id', $school?->id)
                ->where('workflow_state', Material::STATE_CHANGES_REQUESTED)->count(),
        ];

        return view('learning.review.queue', compact('materials', 'counts'));
    }

    /** Full review page for one material, including everything AI produced. */
    public function show(Material $material): View
    {
        $this->authorize('view', $material);

        $material->load([
            'creator', 'reviewer', 'subject', 'classArm.classLevel',
            'flashcards', 'questions', 'studyGuide',
            'notes.user', 'topic.links.linkedTopic',
        ]);

        // Opening the page marks it as being read, so the teacher can see
        // someone picked it up.
        if ($material->workflow_state === Material::STATE_SUBMITTED
            && request()->user()->can('review', $material)) {
            $this->workflow->beginReview($material, request()->user());
            $material->refresh();
        }

        return view('learning.review.show', compact('material'));
    }

    public function submit(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('submit', $material);

        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $this->workflow->submit($material, $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Sent for review.');
    }

    public function approve(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('review', $material);

        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
            'publish' => 'nullable|boolean',
        ]);

        if ($request->boolean('publish')) {
            $this->workflow->approveAndPublish($material, $request->user(), $data['note'] ?? null);

            return back()->with('status', 'Approved and published to students.');
        }

        $this->workflow->approve($material, $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Approved. Publish it when you are ready.');
    }

    public function requestChanges(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('review', $material);

        $data = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $this->workflow->requestChanges($material, $request->user(), $data['note']);

        return back()->with('status', 'Sent back with your notes.');
    }

    public function reject(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('review', $material);

        $data = $request->validate([
            'reason' => 'required|string|max:2000',
        ]);

        $this->workflow->reject($material, $request->user(), $data['reason']);

        return back()->with('status', 'Material rejected.');
    }

    public function publish(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('review', $material);

        $this->workflow->publish($material, $request->user());

        return back()->with('status', 'Published — students can see this now.');
    }

    public function unpublish(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('review', $material);

        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        $this->workflow->unpublish($material, $request->user(), $data['note'] ?? null);

        return back()->with('status', 'Unpublished. Students can no longer see it.');
    }

    public function addNote(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('view', $material);

        $data = $request->validate(['content' => 'required|string|max:2000']);

        $this->workflow->addNote($material, $request->user(), $data['content']);

        return back()->with('status', 'Note added.');
    }
}
