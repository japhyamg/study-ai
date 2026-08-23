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
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function createExam(Request $request): View
    {
        $school = $this->school();

        $classes = ClassArm::with('classLevel')->where('school_id', $school?->id)->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])->values();

        // The exam's subject decides which question bank it can draw on, so it
        // has to be chosen here rather than guessed later.
        $subjects = $this->bankSubjects($request->user(), $school?->id);

        return view('teacher.exams.create', compact('classes', 'subjects'));
    }

    public function storeExam(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $this->validateExam($request);

        $data['school_id'] = $school?->id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = Exam::STATUS_DRAFT;

        $exam = Exam::create($data);

        return redirect()->route('teacher.exams.show', $exam)->with('status', 'Exam created.');
    }

    public function showExam(Request $request, Exam $exam): View
    {
        $this->authorize('view', $exam);

        $exam->load(['questions', 'classArm']);

        // The exam's own subject, not one guessed from the class arm — an arm
        // teaches many subjects, so the old first() picked an arbitrary one.
        $alreadyAdded = $exam->questions->pluck('bank_id')->filter()->all();

        $questionBank = QuestionBank::query()
            ->where('school_id', $this->school()?->id)
            ->forTeacher($request->user())
            ->when($exam->subject_id, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($alreadyAdded, fn ($q) => $q->whereNotIn('id', $alreadyAdded))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->groupBy(fn ($row) => $row->topic ?: 'Other');

        return view('teacher.exams.show', compact('exam', 'questionBank'));
    }

    public function editExam(Request $request, Exam $exam): View
    {
        $this->authorize('update', $exam);
        $school = $this->school();
        $classes = ClassArm::with('classLevel')->where('school_id', $school?->id)->get()
            ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])->values();

        $subjects = $this->bankSubjects($request->user(), $school?->id);

        return view('teacher.exams.edit', compact('exam', 'classes', 'subjects'));
    }

    /**
     * Shared rules for creating and updating an exam.
     *
     * Every setting accepted here is enforced somewhere: duration drives the
     * countdown, max_attempts is checked before an attempt starts, pass_mark
     * decides the pass flag, the start/end window gates entry, and the two
     * shuffle flags are applied when the paper is built for an attempt.
     *
     * negative_marking is still left out — nothing applies it during grading.
     */
    private function validateExam(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'class_arm_id' => 'nullable|exists:class_arms,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'duration' => 'nullable|integer|min:1|max:600',
            'pass_mark' => 'nullable|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1|max:10',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
        ], [
            'end_time.after' => 'The closing time must be after the opening time.',
        ], [
            'class_arm_id' => 'class',
            'duration' => 'duration',
            'start_time' => 'opening time',
            'end_time' => 'closing time',
        ]);

        // A teacher may only tie an exam to a subject they are assigned to,
        // otherwise picking a subject would expose another teacher's bank.
        if (! empty($data['subject_id'])
            && ! $this->canUseBankSubject($request->user(), $data['subject_id'], $this->school()?->id)) {
            throw ValidationException::withMessages([
                'subject_id' => 'You are not assigned to that subject.',
            ]);
        }

        $data['pass_mark'] = $data['pass_mark'] ?? 50;
        $data['max_attempts'] = $data['max_attempts'] ?? 1;

        // Unchecked boxes are absent from the payload entirely, so they have to
        // be written as false rather than left untouched on update.
        $data['shuffle_questions'] = $request->boolean('shuffle_questions');
        $data['shuffle_options'] = $request->boolean('shuffle_options');

        return $data;
    }

    public function updateExam(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        $exam->update($this->validateExam($request));
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
            'type' => ['required', Rule::in(QuestionBank::types())],
            'options' => 'nullable|array|max:6',
            'options.*' => 'nullable|string|max:500',
            'correct_idx' => 'nullable|integer|min:0',
            'answer' => 'nullable|string',
            'explanation' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'points' => 'nullable|numeric|min:0|max:100',
        ]);

        // Same shaping the bank uses, so a question written here and one banked
        // from a study guide are stored identically: answer as text, options
        // trimmed of blanks, written types with no options at all.
        $attributes = $this->bankAttributes($data);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question' => $attributes['question'],
            'type' => $attributes['type'],
            'options' => $attributes['options'],
            'answer' => $attributes['answer'],
            'explanation' => $attributes['explanation'],
            'points' => $data['points'] ?? 1,
            'order' => (int) $exam->questions()->max('order') + 1,
        ]);

        return back()->with('status', 'Question added.');
    }

    public function updateQuestion(Request $request, Exam $exam, ExamQuestion $question): RedirectResponse
    {
        $this->authorize('update', $exam);
        abort_unless($question->exam_id === $exam->id, 404);

        $data = $request->validate([
            'question' => 'required|string',
            'type' => ['required', Rule::in(QuestionBank::types())],
            'options' => 'nullable|array|max:6',
            'options.*' => 'nullable|string|max:500',
            'correct_idx' => 'nullable|integer|min:0',
            'answer' => 'nullable|string',
            'explanation' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'points' => 'nullable|numeric|min:0|max:100',
        ]);

        $attributes = $this->bankAttributes($data);

        $question->update([
            'question' => $attributes['question'],
            'type' => $attributes['type'],
            'options' => $attributes['options'],
            'answer' => $attributes['answer'],
            'explanation' => $attributes['explanation'],
            'points' => $data['points'] ?? $question->points,
        ]);

        return back()->with('status', 'Question updated.');
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
        // Scope to this school first, then widen within it. An ungrouped
        // orWhere here would have matched every material the user created in
        // *any* school, escaping the tenant boundary.
        $materials = Material::with('classArm')
            ->where('school_id', $school?->id)
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
            'subject_id' => 'required|exists:subjects,id',
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

    // ── Flashcard add/edit/delete (from tabbed material detail) ──

    /**
     * Add a card by hand.
     *
     * Generated content is a starting point, not the finished article — a
     * teacher reviewing it will spot gaps the model missed, and should be able
     * to fill them before sending the material on.
     */
    public function storeFlashcard(Request $request, Material $material): RedirectResponse
    {
        // Authorising against the material, not the card: permission to add
        // comes from owning the material it belongs to.
        $this->authorize('update', $material);

        $data = $request->validate([
            'front' => 'required|string|max:2000',
            'back' => 'required|string|max:5000',
        ]);

        $material->flashcards()->create([
            'user_id' => $material->created_by,
            'front' => $data['front'],
            'back' => $data['back'],
            'tags' => [],
            'review_status' => Material::REVIEW_PENDING,
            // Same SM-2 starting position as a generated card, so a
            // hand-written one is scheduled identically.
            'ease_factor' => 2.5,
            'interval' => 0,
            'repetitions' => 0,
            'lapses' => 0,
            'due_date' => now(),
        ]);

        return back()->with('status', 'Flashcard added.');
    }

    public function updateFlashcard(Request $request, Flashcard $flashcard): RedirectResponse
    {
        $this->authorize('update', $flashcard);
        $data = $request->validate([
            'front' => 'required|string|max:2000',
            'back' => 'required|string|max:5000',
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

    // ── Question add/edit/delete (from tabbed material detail) ──

    public function storeQuestion(Request $request, Material $material): RedirectResponse
    {
        $this->authorize('update', $material);

        $data = $this->validateQuestion($request);

        $material->questions()->create($data + ['review_status' => Material::REVIEW_PENDING]);

        return back()->with('status', 'Question added.');
    }

    public function updateQuestion(Request $request, Question $question): RedirectResponse
    {
        $this->authorize('update', $question);

        $question->update($this->validateQuestion($request));

        return back()->with('status', 'Question updated.');
    }

    /**
     * Shared validation for adding and editing a question.
     *
     * `correct_idx` has to be checked against the options actually submitted —
     * a bare `integer` rule would happily store an index pointing past the end
     * of the list, which reads as "no correct answer" everywhere downstream.
     *
     * @return array<string, mixed>
     */
    private function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'question' => 'required|string|max:2000',
            'type' => 'nullable|in:multiple-choice,true-false,fill-blank,short-answer',
            'options' => 'required|array|min:1|max:6',
            'options.*' => 'required|string|max:1000',
            'correct_idx' => 'required|integer|min:0',
            'explanation' => 'nullable|string|max:2000',
            'difficulty' => 'nullable|integer|min:1|max:5',
        ]);

        $options = array_values($data['options']);
        $type = $data['type'] ?? 'multiple-choice';

        // A written answer has no options to choose between: the single entry
        // is the model answer, so the index can only be 0.
        $correct = $type === 'short-answer' ? 0 : (int) $data['correct_idx'];

        if ($correct >= count($options)) {
            throw ValidationException::withMessages([
                'correct_idx' => 'Mark one of the options as the correct answer.',
            ]);
        }

        return [
            'question' => $data['question'],
            'type' => $type,
            'options' => $options,
            'correct_idx' => $correct,
            'explanation' => $data['explanation'] ?? null,
            'difficulty' => $data['difficulty'] ?? 1,
        ];
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

    /**
     * Pull questions out of the bank and onto an exam.
     *
     * This is the point of banking: a teacher building a Maths exam draws on
     * everything approved for Maths over the term rather than writing it all
     * again. The bank row is copied, not referenced, so later edits to the
     * bank do not silently rewrite an exam that has already been sat.
     */
    public function importBankQuestions(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        $data = $request->validate([
            'bank_ids' => 'required|array|min:1',
            'bank_ids.*' => 'string',
        ]);

        // Re-fetch through the teacher's own scope: an id posted from a stale
        // page — or someone else's — must not become a way into another
        // subject's bank.
        $rows = QuestionBank::query()
            ->where('school_id', $this->school()?->id)
            ->forTeacher($request->user())
            ->whereIn('id', $data['bank_ids'])
            ->get();

        if ($rows->isEmpty()) {
            return back()->withErrors(['bank_ids' => 'None of those questions are available to you.']);
        }

        // Adding the same question twice is a mistake, not an intention.
        $existing = $exam->questions()->whereNotNull('bank_id')->pluck('bank_id')->all();
        $rows = $rows->reject(fn ($row) => in_array($row->id, $existing, true));

        if ($rows->isEmpty()) {
            return back()->with('status', 'Those questions are already on this exam.');
        }

        $order = (int) $exam->questions()->max('order');

        DB::transaction(function () use ($rows, $exam, &$order) {
            foreach ($rows as $row) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'bank_id' => $row->id,
                    'question' => $row->question,
                    'type' => $row->type,
                    'options' => $row->options,
                    'answer' => $row->answer,
                    'explanation' => $row->explanation,
                    'points' => 1,
                    'order' => ++$order,
                ]);
            }
        });

        return back()->with('status', $rows->count().' '.Str::plural('question', $rows->count()).' added from the bank.');
    }

    // ── Question bank (teacher) ──
    /**
     * The teacher's question bank.
     *
     * Subject-first: a bank is a subject's accumulated work, and a flat list
     * of several hundred questions across every subject is not something
     * anyone reads. Landing on the subjects — with counts — makes the shape of
     * the bank visible before you commit to opening one.
     */
    public function questionBankIndex(Request $request): View
    {
        $user = $request->user();
        $school = $this->school();

        $subjects = $this->bankSubjects($user, $school?->id);

        // One grouped count query rather than one per subject.
        $counts = QuestionBank::query()
            ->where('school_id', $school?->id)
            ->forTeacher($user)
            ->selectRaw('subject_id, COUNT(*) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id');

        return view('teacher.question-bank.index', [
            'subjects' => $subjects,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
        ]);
    }

    /** One subject's questions, grouped by the study guide they came from. */
    public function questionBankShow(Request $request, Subject $subject): View
    {
        $user = $request->user();
        $school = $this->school();

        abort_unless($this->canUseBankSubject($user, $subject->id, $school?->id), 403);

        $filters = $request->validate([
            'topic' => 'nullable|string',
            'q' => 'nullable|string|max:200',
        ]);

        $questions = QuestionBank::query()
            ->where('school_id', $school?->id)
            ->where('subject_id', $subject->id)
            ->when($filters['topic'] ?? null, fn ($q, $topic) => $q->where('topic', $topic))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('question', 'like', '%'.$term.'%'))
            ->orderBy('topic')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $topics = QuestionBank::query()
            ->where('school_id', $school?->id)
            ->where('subject_id', $subject->id)
            ->whereNotNull('topic')
            ->distinct()
            ->orderBy('topic')
            ->pluck('topic');

        return view('teacher.question-bank.show', [
            'subject' => $subject,
            'questions' => $questions,
            'topics' => $topics,
            'filters' => $filters,
        ]);
    }

    /**
     * Correct a banked question.
     *
     * What a question needs depends on its type, so validation branches on it:
     * a multiple-choice question needs options and a correct one among them,
     * while a written answer needs only the answer itself. Accepting options
     * for a short-answer question would leave data the UI never shows and the
     * grader never reads.
     */
    public function questionBankUpdate(Request $request, QuestionBank $qb): RedirectResponse
    {
        abort_unless($this->canUseBankSubject($request->user(), $qb->subject_id, $qb->school_id), 403);

        $data = $request->validate([
            'question' => 'required|string|max:2000',
            'type' => ['required', Rule::in(QuestionBank::types())],
            'options' => 'nullable|array|max:6',
            'options.*' => 'nullable|string|max:1000',
            'correct_idx' => 'nullable|integer|min:0',
            'answer' => 'nullable|string|max:2000',
            'explanation' => 'nullable|string|max:2000',
            'difficulty' => 'nullable|integer|min:1|max:5',
        ]);

        $qb->update($this->bankAttributes($data));

        return back()->with('status', 'Question updated.');
    }

    /**
     * Normalise a submitted question into what the bank stores.
     *
     * The bank keeps the answer as text rather than an index into the options,
     * so the correct choice has to be resolved here. That is deliberate:
     * options can be reordered or edited later, and an index into a list that
     * has since changed silently points at the wrong answer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function bankAttributes(array $data): array
    {
        $type = $data['type'];
        $choiceBased = in_array($type, [QuestionBank::TYPE_MCQ, QuestionBank::TYPE_TRUE_FALSE], true);

        if (! $choiceBased) {
            // Written answers have nothing to choose between.
            if (trim((string) ($data['answer'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'answer' => 'Give the answer you expect.',
                ]);
            }

            return [
                'question' => $data['question'],
                'type' => $type,
                'options' => null,
                'answer' => trim($data['answer']),
                'explanation' => $data['explanation'] ?? null,
                'difficulty' => $data['difficulty'] ?? 1,
            ];
        }

        $options = array_values(array_filter(
            array_map(static fn ($o) => trim((string) $o), $data['options'] ?? []),
            static fn ($o) => $o !== ''
        ));

        if ($type === QuestionBank::TYPE_TRUE_FALSE) {
            $options = ['True', 'False'];
        }

        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'A choice question needs at least two options.',
            ]);
        }

        $index = (int) ($data['correct_idx'] ?? 0);

        if (! array_key_exists($index, $options)) {
            throw ValidationException::withMessages([
                'correct_idx' => 'Mark one of the options as the correct answer.',
            ]);
        }

        return [
            'question' => $data['question'],
            'type' => $type,
            'options' => $options,
            'answer' => $options[$index],
            'explanation' => $data['explanation'] ?? null,
            'difficulty' => $data['difficulty'] ?? 1,
        ];
    }

    /** Subjects whose bank this user may open. */
    private function bankSubjects(User $user, ?string $schoolId)
    {
        return Subject::where('school_id', $schoolId)
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn(
                'id',
                ClassSubjectAssignment::where('teacher_id', $user->id)->select('subject_id')
            ))
            ->orderBy('name')
            ->get();
    }

    public function questionBankStore(Request $request): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            // Required: a bank belongs to a subject, and a question filed
            // under nothing is invisible everywhere it would be used.
            'subject_id' => 'required|exists:subjects,id',
            'question' => 'required|string|max:2000',
            'type' => ['required', Rule::in(QuestionBank::types())],
            'options' => 'nullable|array|max:6',
            'options.*' => 'nullable|string|max:1000',
            'correct_idx' => 'nullable|integer|min:0',
            'answer' => 'nullable|string|max:2000',
            'explanation' => 'nullable|string|max:2000',
            'difficulty' => 'nullable|integer|min:1|max:5',
        ]);

        abort_unless(
            $this->canUseBankSubject($request->user(), $data['subject_id'], $school?->id),
            403
        );

        // Shared with the editor, so a hand-written question is shaped exactly
        // like one that arrived from an approved quiz.
        QuestionBank::create($this->bankAttributes($data) + [
            'school_id' => $school?->id,
            'subject_id' => $data['subject_id'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Question added to the bank.');
    }

    public function questionBankDestroy(Request $request, QuestionBank $qb): RedirectResponse
    {
        // School alone is not enough: a bank belongs to a subject, and a
        // teacher who does not teach it has no business editing it.
        abort_unless($this->canUseBankSubject($request->user(), $qb->subject_id, $qb->school_id), 403);

        $qb->delete();

        return back()->with('status', 'Question removed from the bank.');
    }

    /**
     * May this user read and write the bank for a subject?
     *
     * Admins oversee the whole school. A teacher is limited to the subjects
     * they are actually assigned to, which is what makes the bank feel like
     * their own rather than a shared dumping ground.
     */
    private function canUseBankSubject(User $user, ?string $subjectId, ?string $schoolId): bool
    {
        if ($schoolId !== $this->school()?->id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $subjectId) {
            return false;
        }

        return ClassSubjectAssignment::where('teacher_id', $user->id)
            ->where('subject_id', $subjectId)
            ->exists();
    }
}
