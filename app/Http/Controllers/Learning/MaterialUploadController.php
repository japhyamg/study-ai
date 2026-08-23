<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiContent;
use App\Models\ClassArm;
use App\Models\Material;
use App\Models\ProcessingJob;
use App\Models\Subject;
use App\Services\Learning\MaterialParserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/**
 * Upload a document (or paste text), extract it, and queue AI generation.
 *
 * Extraction happens in the request rather than the job, deliberately: if a
 * PDF is a scan with no text layer, the teacher finds out immediately with a
 * message they can act on, instead of a job failing silently minutes later.
 * Only the model calls — the slow, retryable part — go to the queue.
 */
class MaterialUploadController extends Controller
{
    public function __construct(private MaterialParserService $parser) {}

    public function create(Request $request): View
    {
        $this->authorize('create', Material::class);

        $school = $request->user()->currentSchool();

        $classes = ClassArm::with('classLevel')
            ->where('school_id', $school?->id)
            ->get()
            ->sortBy(fn (ClassArm $arm) => [$arm->classLevel?->position ?? 0, $arm->name])
            ->values();

        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();

        return view('learning.upload', compact('classes', 'subjects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Material::class);

        $accepted = implode(',', config('ai.uploads.accepted', ['pdf', 'docx', 'txt']));

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'subject_id' => 'nullable|exists:subjects,id',

            'document' => "nullable|file|mimes:{$accepted}|max:".config('ai.uploads.max_size_kb', 20480),
            'content' => 'nullable|string',

            'question_count' => 'nullable|integer|min:3|max:30',
            'question_types' => 'nullable|array',
            'question_types.*' => 'in:multiple-choice,true-false,fill-blank,short-answer',
            'generate' => 'nullable|boolean',
        ]);

        if (! $request->hasFile('document') && blank($data['content'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['document' => 'Upload a document or paste the text you want to use.']);
        }

        // Extract first — nothing is written if the file is unreadable.
        try {
            $text = $request->hasFile('document')
                ? $this->parser->parse($request->file('document'))
                : $this->parser->parse($data['content']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['document' => $e->getMessage()]);
        }

        $school = $request->user()->currentSchool();
        $file = $request->file('document');
        $storedPath = null;

        if ($file) {
            $storedPath = $file->store(
                config('ai.uploads.path', 'materials'),
                config('ai.uploads.disk', 'local')
            );
        }

        $config = [
            'questionCount' => (int) ($data['question_count'] ?? config('ai.defaults.question_count', 10)),
            'questionTypes' => $data['question_types'] ?? config('ai.defaults.question_types', ['multiple-choice']),
        ];

        $material = Material::create([
            'school_id' => $school?->id,
            'class_arm_id' => $data['class_arm_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $file ? $this->parser->detectType($file) : 'note',
            'content' => $text,
            'file_path' => $storedPath,
            'file_name' => $file?->getClientOriginalName(),
            'file_type' => $file ? $this->parser->detectType($file) : null,
            'file_size' => $file?->getSize(),
            'generation_config' => $config,
            'status' => Material::STATUS_DRAFT,
            'workflow_state' => Material::STATE_DRAFT,
            'review_status' => Material::REVIEW_PENDING,
            'published' => false,
            'created_by' => $request->user()->id,
        ]);

        if ($request->boolean('generate', true)) {
            $this->queueGeneration($material, ProcessingJob::TYPE_ALL, $config);

            return redirect()
                ->route('learning.materials.show', $material)
                ->with('status', 'Uploaded. Generating study content now — this page updates as it finishes.');
        }

        return redirect()
            ->route('learning.materials.show', $material)
            ->with('status', 'Material saved as a draft.');
    }

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
