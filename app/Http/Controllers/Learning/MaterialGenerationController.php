<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiContent;
use App\Models\Material;
use App\Models\ProcessingJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Running AI generation for a material that already has extracted text.
 *
 * Upload and text extraction happen in TeacherController::materialsStore;
 * generation is a separate, deliberate step so the teacher can see what was
 * actually pulled out of their file before spending tokens on it. First run
 * and re-run are the same operation — the only difference is whether anything
 * gets replaced.
 */
class MaterialGenerationController extends Controller
{
    public function regenerate(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $data = $request->validate([
            'type' => 'required|in:generate_flashcards,generate_questions,generate_study_guide,generate_all',
            'question_count' => 'nullable|integer|min:3|max:30',
            'question_types' => 'nullable|array',
            'question_types.*' => 'in:multiple-choice,true-false,fill-blank,short-answer',
        ]);

        if ($material->sourceText() === '') {
            return back()->withErrors([
                'type' => 'This material has no text to work from. Edit it and add content first.',
            ]);
        }

        if ($material->isProcessing()) {
            return back()->withErrors(['type' => 'Generation is already running for this material.']);
        }

        $config = [
            'questionCount' => (int) ($data['question_count'] ?? $material->generation_config['questionCount'] ?? 10),
            'questionTypes' => $data['question_types']
                ?? $material->generation_config['questionTypes']
                ?? ['multiple-choice'],
        ];

        $hadContent = $material->hasGeneratedContent();

        // Remember the choice so the next run defaults to it.
        $material->update(['generation_config' => $config]);

        $this->queueGeneration($material, $data['type'], $config);

        // On the sync driver the job has already run to completion by the
        // time we get here, so "this will take a moment" would be a lie —
        // and it sat next to the failure panel contradicting it. Report the
        // state the material is actually in.
        $material->refresh();

        if ($material->workflow_state === Material::STATE_AI_FAILED) {
            // The panel on the page already carries the reason and the
            // reference; a second copy in a flash would just be noise.
            return back();
        }

        if ($material->workflow_state === Material::STATE_AI_COMPLETED) {
            return back()->with('status', $hadContent
                ? 'Content regenerated.'
                : 'Study content generated.');
        }

        return back()->with('status', $hadContent
            ? 'Regenerating — this will take a moment.'
            : 'Generating study content — this will take a moment.');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function queueGeneration(Material $material, string $type, array $config): ProcessingJob
    {
        $job = ProcessingJob::create([
            'type' => $type,
            'status' => ProcessingJob::STATUS_PENDING,
            'school_id' => $material->school_id,
            'material_id' => $material->id,
            'created_by' => auth()->id(),
            'progress' => 0,
            'result' => $config,
        ]);

        $material->transitionTo(Material::STATE_AI_PROCESSING);

        // With the sync driver there is no worker, so run it inline; the
        // request will block, but a dev environment has no alternative.
        if (config('queue.default') === 'sync') {
            app(\App\Services\AiContentService::class)->runJob($job->refresh());
        } else {
            GenerateAiContent::dispatch($job);
        }

        return $job;
    }
}
