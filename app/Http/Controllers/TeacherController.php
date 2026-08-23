<?php

namespace App\Http\Controllers;

use App\Models\ClassArm;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\ProcessingJob;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudyGuide;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:teacher,admin,super_admin');
    }

    protected function school(): ?School
    {
        return auth()->user()?->currentSchool();
    }

    // ── Dashboard ──
    public function dashboard(): View
    {
        $user = auth()->user();
        $school = $this->school();
        $myClasses = ClassArm::where('school_id', $school?->id)
            ->where(fn ($q) => $q
                ->where('form_teacher_id', $user->id)
                ->orWhereHas('subjectAssignments', fn ($a) => $a->where('teacher_id', $user->id)))
            ->with('classLevel')
            ->withCount('enrollments')
            ->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])
            ->values();

        $myExams = Exam::where('school_id', $school?->id)
            ->where('created_by', $user->id)
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $pendingMaterials = Material::where('school_id', $school?->id)
            ->awaitingReview()
            ->with('classArm')
            ->get();

        return view('teacher.dashboard', compact('myClasses', 'myExams', 'pendingMaterials'));
    }

    // ── Teacher's classes ──
    public function teacherClasses(): View
    {
        $user = auth()->user();
        $school = $this->school();
        $classes = ClassArm::where('school_id', $school?->id)
            ->where(fn ($q) => $q
                ->where('form_teacher_id', $user->id)
                ->orWhereHas('subjectAssignments', fn ($a) => $a->where('teacher_id', $user->id)))
            ->with(['classLevel', 'subjectAssignments.subject'])
            ->withCount('enrollments')
            ->orderBy('name')
            ->paginate(20);

        return view('teacher.classes.index', compact('classes'));
    }

    public function teacherClassShow(ClassArm $class): View
    {
        $this->authorize('view', $class);
        $class->load([
            'classLevel', 'formTeacher', 'subjectAssignments.subject', 'subjectAssignments.teacher',
            'enrollments.user', 'materials',
            'exams' => fn ($q) => $q->withCount('attempts'),
        ]);
        return view('teacher.classes.show', compact('class'));
    }

    // ── Exams ──
    public function exams(Request $request): View
    {
        $user = auth()->user();
        $school = $this->school();
        $query = Exam::with(['classArm', 'questions'])
            ->where('school_id', $school?->id)
            ->orderBy('created_at', 'desc');

        $status = $request->get('status');
        if (in_array($status, [Exam::STATUS_DRAFT, Exam::STATUS_PUBLISHED, Exam::STATUS_ARCHIVED])) {
            $query->where('status', $status);
        }

        $exams = $query->paginate(20)->withQueryString();

        return view('teacher.exams.index', compact('exams', 'status'));
    }

    public function createExam(): View
    {
        $school = $this->school();
        $classes = ClassArm::with('classLevel')->where('school_id', $school?->id)->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])->values();
        return view('teacher.exams.create', compact('classes'));
    }

    public function storeExam(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'duration_minutes' => 'nullable|integer|min:1',
            'pass_mark' => 'nullable|numeric|min:0|max:100',
        ]);

        $data['school_id'] = $school?->id;
        $data['created_by'] = auth()->id();
        $data['status'] = Exam::STATUS_DRAFT;
        $data['pass_mark'] = $data['pass_mark'] ?? 50;

        $exam = Exam::create($data);

        return redirect()->route('teacher.exams.show', $exam)->with('status', 'Exam created.');
    }

    public function showExam(Exam $exam): View
    {
        $this->authorize('view', $exam);
        $exam->load(['questions', 'classArm']);
        $questionBank = QuestionBank::where('school_id', $this->school()?->id)
            ->when($exam->class_arm_id, fn ($q) => $q->where('subject_id', $exam->classArm?->subjectAssignments->first()?->subject_id))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
        return view('teacher.exams.show', compact('exam', 'questionBank'));
    }

    public function editExam(Exam $exam): View
    {
        $this->authorize('update', $exam);
        $school = $this->school();
        $classes = ClassArm::with('classLevel')->where('school_id', $school?->id)->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])->values();
        return view('teacher.exams.edit', compact('exam', 'classes'));
    }

    public function updateExam(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'duration_minutes' => 'nullable|integer|min:1',
            'pass_mark' => 'nullable|numeric|min:0|max:100',
        ]);
        $exam->update($data);
        return redirect()->route('teacher.exams.show', $exam)->with('status', 'Exam updated.');
    }

    public function publishExam(Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        if ($exam->questions()->count() === 0) {
            return back()->withErrors(['publish' => 'Add at least one question before publishing.']);
        }
        $exam->update(['status' => Exam::STATUS_PUBLISHED]);
        return back()->with('status', 'Exam published.');
    }

    public function unpublishExam(Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $exam->update(['status' => Exam::STATUS_DRAFT]);
        return back()->with('status', 'Exam unpublished.');
    }

    public function destroyExam(Exam $exam): RedirectResponse
    {
        $this->authorize('delete', $exam);
        $exam->delete();
        return redirect()->route('teacher.exams.index')->with('status', 'Exam deleted.');
    }

    // ── Exam questions ──
    public function addQuestion(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);
        $data = $request->validate([
            'question' => 'required|string',
            'type' => 'required|string|max:50',
            'options' => 'nullable|array',
            'answer' => 'nullable|string',
            'explanation' => 'nullable|string',
            'points' => 'nullable|numeric|min:0',
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => $data['question'],
            'type' => $data['type'],
            'options' => $data['options'] ?? null,
            'answer' => $data['answer'] ?? null,
            'explanation' => $data['explanation'] ?? null,
            'points' => $data['points'] ?? 1,
            'order' => $exam->questions()->count() + 1,
        ]);

        return back()->with('status', 'Question added.');
    }

    public function removeQuestion(Exam $exam, ExamQuestion $question): RedirectResponse
    {
        $this->authorize('update', $exam);
        if ($question->exam_id !== $exam->id) {
            abort(403);
        }
        $question->delete();
        return back()->with('status', 'Question removed from exam.');
    }

    // ── Materials review (teacher) ──
    public function reviewMaterials(): View
    {
        $school = $this->school();
        $materials = Material::with(['classArm', 'creator'])
            ->where('school_id', $school?->id)
            ->orderByRaw("CASE workflow_state
                WHEN 'submitted' THEN 1
                WHEN 'under_review' THEN 2
                WHEN 'changes_requested' THEN 3
                ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return view('teacher.materials.review', compact('materials'));
    }

    public function approveMaterial(Material $material): RedirectResponse
    {
        $school = $this->school();
        if ($material->school_id !== $school?->id) {
            abort(403);
        }
        app(\App\Services\Learning\MaterialWorkflowService::class)
            ->approveAndPublish($material, auth()->user());

        return back()->with('status', 'Material approved and published.');
    }

    public function rejectMaterial(Request $request, Material $material): RedirectResponse
    {
        $school = $this->school();
        if ($material->school_id !== $school?->id) {
            abort(403);
        }
        $data = $request->validate(['review_notes' => 'required|string|max:2000']);

        app(\App\Services\Learning\MaterialWorkflowService::class)
            ->reject($material, auth()->user(), $data['review_notes']);

        return back()->with('status', 'Material rejected.');
    }

    // ── Materials management (teacher) ──
    public function materialsIndex(): View
    {
        $school = $this->school();
        $materials = Material::with('classArm')
            ->where('created_by', auth()->id())
            ->orWhere('school_id', $school?->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return view('teacher.materials.index', compact('materials'));
    }

    public function materialsCreate(): View
    {
        $school = $this->school();
        $classes = ClassArm::with('classLevel')->where('school_id', $school?->id)->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])->values();
        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();
        return view('teacher.materials.create', compact('classes', 'subjects'));
    }

    public function materialsStore(Request $request): RedirectResponse
    {
        $school = $this->school();
        $accepted = implode(',', config('ai.uploads.accepted', ['pdf', 'docx', 'txt']));

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'nullable|in:note,pdf,pptx,youtube,video,doc,link,url',
            'content' => 'nullable|string',
            'source_url' => 'nullable|url',
            'document' => "nullable|file|mimes:{$accepted}|max:".config('ai.uploads.max_size_kb', 20480),
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'question_count' => 'nullable|integer|min:3|max:30',
            'question_types' => 'nullable|array',
            'question_types.*' => 'in:multiple-choice,true-false,fill-blank,short-answer',
            'generate' => 'nullable|boolean',
        ]);

        $file = $request->file('document');

        if (! $file && blank($data['content'] ?? null) && blank($data['source_url'] ?? null)) {
            return back()->withInput()->withErrors([
                'document' => 'Upload a file, paste some text, or give a link.',
            ]);
        }

        // Extract in-request so an unreadable file (a scan, say) fails now with
        // a message the teacher can act on, rather than in a queued job later.
        $parser = app(\App\Services\Learning\MaterialParserService::class);
        $text = $data['content'] ?? null;
        $storedPath = null;

        if ($file) {
            try {
                $text = $parser->parse($file);
            } catch (\RuntimeException $e) {
                return back()->withInput()->withErrors(['document' => $e->getMessage()]);
            }

            $storedPath = $file->store(
                config('ai.uploads.path', 'materials'),
                config('ai.uploads.disk', 'local')
            );
        }

        $type = $file
            ? $parser->detectType($file)
            : ($data['type'] ?? (filled($data['source_url'] ?? null) ? 'link' : 'note'));

        $material = Material::create([
            'school_id' => $school?->id,
            'class_arm_id' => $data['class_arm_id'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'content' => $text,
            'source_url' => $data['source_url'] ?? null,
            'file_path' => $storedPath,
            'file_name' => $file?->getClientOriginalName(),
            'file_type' => $file ? $type : null,
            'file_size' => $file?->getSize(),
            'generation_config' => [
                'questionCount' => (int) ($data['question_count'] ?? config('ai.defaults.question_count', 10)),
                'questionTypes' => $data['question_types'] ?? ['multiple-choice'],
            ],
            'status' => Material::STATUS_DRAFT,
            'workflow_state' => Material::STATE_DRAFT,
            'review_status' => Material::REVIEW_PENDING,
            'published' => false,
            'created_by' => auth()->id(),
        ]);

        // Generation needs text; a bare link has none.
        if ($request->boolean('generate') && filled($text)) {
            $questionCount = (int) ($data['question_count'] ?? 10);
            $questionTypes = $data['question_types'] ?? ['multiple-choice'];
            $job = ProcessingJob::create([
                'type' => ProcessingJob::TYPE_ALL,
                'status' => ProcessingJob::STATUS_PENDING,
                'school_id' => $school?->id,
                'material_id' => $material->id,
                'created_by' => auth()->id(),
                'progress' => 0,
                'result' => [
                    'questionCount' => $questionCount,
                    'questionTypes' => $questionTypes,
                ],
            ]);
            $material->transitionTo(Material::STATE_AI_PROCESSING);
            if (config('queue.default') === 'sync') {
                app(\App\Services\AiContentService::class)->runJob($job);
            } else {
                \App\Jobs\GenerateAiContent::dispatch($job);
            }
        }

        return redirect()->route('learning.materials.show', $material)
            ->with('status', $request->boolean('generate') && filled($text)
                ? 'Uploaded. Generating study content now.'
                : 'Saved as a draft.');
    }

    public function materialsEdit(Material $material): View
    {
        $this->authorize('update', $material);
        $school = $this->school();
        $classes = ClassArm::with('classLevel')->where('school_id', $school?->id)->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])->values();
        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();
        return view('teacher.materials.edit', compact('material', 'classes', 'subjects'));
    }

    public function materialsUpdate(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'content' => 'nullable|string',
            'source_url' => 'nullable|url',
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'status' => 'nullable|in:draft,processing,ready,failed',
        ]);
        $material->update($data);
        return back()->with('status', 'Material updated.');
    }

    public function materialsShow(Material $material): View
    {
        $this->authorize('view', $material);
        $material->load(['flashcards', 'questions', 'studyGuide', 'classArm', 'subject']);
        return view('teacher.materials.show', compact('material'));
    }

    public function materialsApproveAll(Material $material): RedirectResponse
    {
        $this->authorize('update', $material);
        // approve all pending flashcards/questions, then publish the material
        Flashcard::where('material_id', $material->id)->where('review_status', Material::REVIEW_PENDING)
            ->update(['review_status' => Material::REVIEW_APPROVED]);
        Question::where('material_id', $material->id)->where('review_status', Material::REVIEW_PENDING)
            ->update(['review_status' => Material::REVIEW_APPROVED]);
        app(\App\Services\Learning\MaterialWorkflowService::class)
            ->approveAndPublish($material, auth()->user());

        return redirect()->route('teacher.materials.index')
            ->with('status', 'Material approved and published.');
    }

    public function destroyMaterial(Material $material): RedirectResponse
    {
        $this->authorize('delete', $material);
        $material->delete();
        return redirect()->route('teacher.materials.index')->with('status', 'Material deleted.');
    }

    // ── Flashcard inline edit/delete (from tabbed material detail) ──
    public function updateFlashcard(Request $request, Flashcard $flashcard): RedirectResponse
    {
        $this->authorize('update', $flashcard);
        $data = $request->validate([
            'front' => 'required|string', 'back' => 'required|string',
        ]);
        $flashcard->update($data);
        return back()->with('status', 'Flashcard updated.');
    }

    public function destroyFlashcard(Flashcard $flashcard): RedirectResponse
    {
        $this->authorize('delete', $flashcard);
        $flashcard->delete();
        return back()->with('status', 'Flashcard deleted.');
    }

    // ── Question edit/delete (from tabbed material detail) ──
    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $this->authorize('update', $question);
        $data = $request->validate([
            'question' => 'required|string',
            'options' => 'nullable|array',
            'correct_idx' => 'nullable|integer',
            'explanation' => 'nullable|string',
        ]);
        $question->update($data);
        return back()->with('status', 'Question updated.');
    }

    public function destroyQuestion(Question $question): RedirectResponse
    {
        $this->authorize('delete', $question);
        $question->delete();
        return back()->with('status', 'Question deleted.');
    }

    // ── Exam analytics (teacher) ──
    public function examAnalytics(Exam $exam): View
    {
        $this->authorize('view', $exam);
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(30);
        $avg = (float) ExamAttempt::where('exam_id', $exam->id)->where('submitted', true)->avg('percentage');
        $passRate = (float) ExamAttempt::where('exam_id', $exam->id)->where('submitted', true)
            ->where('passed', true)->count();
        $total = (float) ExamAttempt::where('exam_id', $exam->id)->where('submitted', true)->count();
        return view('teacher.exams.analytics', compact('exam', 'attempts', 'avg', 'passRate', 'total'));
    }

    // ── Question bank (teacher) ──
    public function questionBankIndex(): View
    {
        $school = $this->school();
        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();
        $questions = QuestionBank::where('school_id', $school?->id)
            ->with('subject')
            ->orderBy('created_at', 'desc')
            ->paginate(30);
        return view('teacher.question-bank.index', compact('questions', 'subjects'));
    }

    public function questionBankStore(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'question' => 'required|string',
            'type' => 'required|in:mcq,true_false,fill_blank,short_answer,essay',
            'options' => 'nullable|array',
            'answer' => 'required|string',
            'explanation' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);
        QuestionBank::create([
            'school_id' => $school?->id,
            'subject_id' => $data['subject_id'] ?? null,
            'question' => $data['question'],
            'type' => $data['type'],
            'options' => $data['options'] ?? null,
            'answer' => $data['answer'],
            'explanation' => $data['explanation'] ?? null,
            'difficulty' => $data['difficulty'] ?? 1,
            'created_by' => auth()->id(),
        ]);
        return back()->with('status', 'Question added to bank.');
    }

    public function questionBankDestroy(QuestionBank $qb): RedirectResponse
    {
        $school = $this->school();
        if ($qb->school_id !== $school?->id) {
            abort(403);
        }
        $qb->delete();
        return back()->with('status', 'Question removed.');
    }
}
