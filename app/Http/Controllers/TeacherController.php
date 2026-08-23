<?php

namespace App\Http\Controllers;

use App\Models\ClassArm;
use App\Models\ClassSubjectAssignment;
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
        return view('teacher.materials.create', $this->assignmentOptions());
    }

    /**
     * Class and subject choices for the material forms.
     *
     * A teacher writes material for the subjects they actually teach, so these
     * come from their own subject assignments rather than the school's full
     * catalogue. Admins are not assigned to classes but must be able to file
     * material against any of them, so they still see everything — as does a
     * teacher with no assignments yet, since that is an admin setup gap rather
     * than a reason to lock them out.
     *
     * @return array<string, mixed>
     */
    private function assignmentOptions(?Material $material = null): array
    {
        $user = auth()->user();
        $school = $this->school();

        $assignments = ClassSubjectAssignment::with(['classArm.classLevel', 'subject'])
            ->where('school_id', $school?->id)
            ->where('teacher_id', $user->id)
            ->get();

        $isAdmin = $user->roleInSchool() === SchoolMember::ROLE_ADMIN;
        $scoped = ! $isAdmin && $assignments->isNotEmpty();

        if ($scoped) {
            $classes = $assignments->pluck('classArm')
                ->filter()
                ->unique('id')
                ->sortBy(fn ($arm) => [$arm->classLevel?->position ?? 0, $arm->name])
                ->values();

            $subjects = $assignments->pluck('subject')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values();
        } else {
            $classes = ClassArm::with('classLevel')
                ->where('school_id', $school?->id)
                ->get()
                ->sortBy(fn ($arm) => [$arm->classLevel?->position ?? 0, $arm->name])
                ->values();

            $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();
        }

        // When editing, keep whatever the material is already filed under in
        // the list even if the teacher's assignments have since changed —
        // otherwise saving the form would silently move it somewhere else.
        if ($scoped && $material) {
            if ($material->classArm && ! $classes->contains('id', $material->class_arm_id)) {
                $classes = $classes->push($material->classArm)->values();
            }

            if ($material->subject && ! $subjects->contains('id', $material->subject_id)) {
                $subjects = $subjects->push($material->subject)->values();
            }
        }

        return [
            'classes' => $classes,
            'subjects' => $subjects,
            // Which subjects belong to which arm, so choosing a class can
            // narrow the subject list in the browser.
            'subjectsByClass' => $assignments
                ->groupBy('class_arm_id')
                ->map(fn ($group) => $group->pluck('subject_id')->filter()->values()->all()),
            // A real state worth naming, not an error.
            'unassigned' => ! $isAdmin && $assignments->isEmpty(),
        ];
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
        ]);

        // The form only offers the teacher's own class/subject pairs, but the
        // form is not the security boundary — check the posted pair too.
        if ($error = $this->assignmentError($data['class_arm_id'] ?? null, $data['subject_id'] ?? null)) {
            return back()->withInput()->withErrors($error);
        }

        $file = $request->file('document');

        if (! $file && blank($data['content'] ?? null) && blank($data['source_url'] ?? null)) {
            return back()->withInput()->withErrors([
                'document' => 'Upload a file, paste some text, or give a link.',
            ]);
        }

        // An uploaded document is stored as a file and parsed on demand — the
        // extracted text is never copied into the database. Parse once here
        // anyway, purely to validate: an unreadable file (a scan, say) should
        // be rejected now, with a message the teacher can act on, rather than
        // silently producing nothing at generation time.
        $parser = app(\App\Services\Learning\MaterialParserService::class);
        $text = $data['content'] ?? null;
        $storedPath = null;

        if ($file) {
            try {
                $extracted = $parser->parse($file);
            } catch (\RuntimeException $e) {
                return back()->withInput()->withErrors(['document' => $e->getMessage()]);
            }

            $storedPath = $file->store(
                config('ai.uploads.path', 'materials'),
                config('ai.uploads.disk', 'local')
            );

            if (! $storedPath) {
                return back()->withInput()->withErrors([
                    'document' => 'The file could not be saved. Try again.',
                ]);
            }

            // The file is the source of truth from here on; keep only enough
            // to describe it in the UI without re-reading it.
            $text = null;
            $extractedLength = mb_strlen($extracted);
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
                'questionCount' => (int) config('ai.defaults.question_count', 10),
                'questionTypes' => config('ai.defaults.question_types', ['multiple-choice']),
            ],
            'status' => Material::STATUS_DRAFT,
            'workflow_state' => Material::STATE_DRAFT,
            'review_status' => Material::REVIEW_PENDING,
            'published' => false,
            'created_by' => auth()->id(),
        ]);

        // Generation is a separate, deliberate step on the material's own
        // page — the teacher picks question count and types there, having
        // seen what was actually extracted.
        $status = match (true) {
            isset($extractedLength) => 'Uploaded — '.number_format($extractedLength)
                .' characters read. Review it, then generate study content.',
            filled($text) => 'Saved. Review it, then generate study content.',
            default => 'Saved as a draft.',
        };

        return redirect()->route('learning.materials.show', $material)->with('status', $status);
    }

    /**
     * Reject a (class, subject) pair the teacher is not assigned to.
     *
     * Admins are exempt: they are not assigned to classes but do need to file
     * material against any of them.
     *
     * @return array<string, string>|null validation errors, or null if allowed
     */
    private function assignmentError(?string $classArmId, ?string $subjectId): ?array
    {
        $user = auth()->user();

        if ($user->roleInSchool() === SchoolMember::ROLE_ADMIN) {
            return null;
        }

        if (! $classArmId && ! $subjectId) {
            return null;
        }

        $assignments = ClassSubjectAssignment::where('teacher_id', $user->id);

        // A teacher with no assignments at all is not blocked — that is an
        // admin setup gap, not an attempt to reach someone else's class.
        if (! (clone $assignments)->exists()) {
            return null;
        }

        if ($classArmId && $subjectId) {
            $allowed = (clone $assignments)
                ->where('class_arm_id', $classArmId)
                ->where('subject_id', $subjectId)
                ->exists();

            return $allowed ? null : ['subject_id' => 'You do not teach that subject in that class.'];
        }

        if ($classArmId && ! (clone $assignments)->where('class_arm_id', $classArmId)->exists()) {
            return ['class_arm_id' => 'You do not teach that class.'];
        }

        if ($subjectId && ! (clone $assignments)->where('subject_id', $subjectId)->exists()) {
            return ['subject_id' => 'You do not teach that subject.'];
        }

        return null;
    }

    public function materialsEdit(Material $material): View
    {
        $this->authorize('update', $material);

        return view('teacher.materials.edit', $this->assignmentOptions($material) + compact('material'));
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

        // Moving a material to a class/subject the teacher does not take is
        // the same problem as filing it there in the first place.
        if ($error = $this->assignmentError($data['class_arm_id'] ?? null, $data['subject_id'] ?? null)) {
            return back()->withInput()->withErrors($error);
        }

        // For an uploaded document the file is the source of truth, so a
        // pasted-content edit would be silently ignored at generation time.
        // Drop it rather than store something that has no effect.
        if ($material->file_path) {
            unset($data['content']);
        }

        $material->update($data);

        // Editing pasted content changes what the AI should see, so the
        // structured cache has to go with it.
        if (array_key_exists('content', $data)) {
            $material->forgetStructuredContent();
        }

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
