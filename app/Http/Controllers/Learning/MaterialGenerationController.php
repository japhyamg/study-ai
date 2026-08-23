<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiContent;
use App\Models\Material;
use App\Models\ProcessingJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Re-running AI generation for a material that already has extracted text.
 *
 * Creating a material (including the upload and text extraction) is handled by
 * TeacherController::materialsStore — there is one upload form, not two.
 */
class MaterialGenerationController extends Controller
{
    /** Re-run generation for a material that already has text. */
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

        $this->queueGeneration($material, $data['type'], $config);

        return back()->with('status', 'Regenerating — this will take a moment.');
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
