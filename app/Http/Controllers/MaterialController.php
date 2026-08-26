<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAiContent;
use App\Models\Material;
use App\Models\ProcessingJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialController extends Controller
{
    /**
     * Trigger AI generation for a material (flashcards / questions / study-guide / all).
     */
    public function generate(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $type = $request->validate([
            'type' => 'required|in:generate_flashcards,generate_questions,generate_study_guide,generate_all',
            'question_count' => 'nullable|integer|min:3|max:30',
            'question_types' => 'nullable|array',
            'question_types.*' => 'in:multiple-choice,true-false,fill-blank,short-answer',
        ])['type'];

        $jobResult = null;
        if ($type === ProcessingJob::TYPE_ALL || $type === ProcessingJob::TYPE_QUESTIONS) {
            $jobResult = [
                'questionCount' => $request->input('question_count', 10),
                'questionTypes' => $request->input('question_types', ['multiple-choice']),
            ];
        }

        $job = ProcessingJob::create([
            'type' => $type,
            'status' => ProcessingJob::STATUS_PENDING,
            'school_id' => $material->school_id,
            'material_id' => $material->id,
            'created_by' => auth()->id(),
            'progress' => 0,
            'result' => $jobResult,
        ]);

        $material->transitionTo(Material::STATE_AI_PROCESSING);

        // Dispatch to queue; if no queue worker is running, process inline.
        if (config('queue.default') === 'sync') {
            $job->refresh();
            app(\App\Services\AiContentService::class)->runJob($job);
        } else {
            GenerateAiContent::dispatch($job);
        }

        return back()->with('status', 'AI generation started.');
    }

    public function jobStatus(ProcessingJob $job)
    {
        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'error' => $job->error,
            'result' => $job->result,
        ]);
    }

    /**
     * Show generated study guide / flashcards for a material (student/teacher view).
     */
    public function show(Material $material): View
    {
        $material->load(['flashcards', 'studyGuide', 'questions']);
        return view('materials.show', compact('material'));
    }
}
