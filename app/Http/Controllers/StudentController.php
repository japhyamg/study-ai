<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassSubjectAssignment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Services\Learning\ExamPaperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:student,admin,super_admin');
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

        $enrollments = ClassEnrollment::with('classArm.classLevel')
            ->where('user_id', $user->id)
            ->get();
        $classIds = $enrollments->pluck('class_arm_id')->filter();

        // The class filter is grouped: left ungrouped, the OR escapes the school
        // and status conditions and starts matching other tenants' exams.
        $availableExams = Exam::with('subject')
            ->where('school_id', $school?->id)
            ->where('status', Exam::STATUS_PUBLISHED)
            ->where(function ($q) use ($classIds) {
                $q->whereIn('class_arm_id', $classIds)->orWhereNull('class_arm_id');
            })
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get();

        // Upcoming exams (next 3 with a future/present start time, else latest published)
        $upcomingExams = (clone $availableExams)
            ->filter(fn ($e) => !$e->start_time || $e->start_time->isFuture())
            ->sortBy('start_time')
            ->take(3)
            ->values();

        // The subjects this student is taught, which is how they navigate now.
        $subjects = ClassSubjectAssignment::with(['subject', 'teacher'])
            ->whereIn('class_arm_id', $classIds)
            ->get()
            ->filter(fn ($a) => $a->subject !== null)
            ->unique('subject_id')
            ->sortBy(fn ($a) => $a->subject->name)
            ->values();

        $visibleFlashcards = $this->visibleFlashcardsQuery($user);

        $stats = [
            'subjects' => $subjects->count(),
            'dueFlashcards' => (clone $visibleFlashcards)
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()))
                ->count(),
            'upcomingExams' => $upcomingExams->count(),
        ];

        $recentAttempts = ExamAttempt::with('exam')
            ->where('user_id', $user->id)
            ->where('submitted', true)
            ->orderBy('end_time', 'desc')
            ->limit(5)
            ->get();

        return view('student.dashboard', compact(
            'subjects', 'availableExams', 'upcomingExams', 'stats', 'recentAttempts'
        ));
    }

    /**
     * Flashcards visible to a student: their own cards, plus teacher-generated
     * cards on published school materials they haven't cloned yet.
     */
    public static function visibleFlashcardsQuery($user)
    {
        $sid = $user->currentSchool()?->id;
        return Flashcard::query()
            ->where(function ($q) use ($user, $sid) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('material', fn ($m) => $m->published()->where('school_id', $sid));
            })
            // hide template cards the student already has a personal copy of
            ->whereRaw('not exists (select 1 from flashcards mine where mine.user_id = ? and mine.material_id = flashcards.material_id and mine.front = flashcards.front)', [$user->id]);
    }

    // ── Subjects ──

    /**
     * The subjects this student is taught.
     *
     * A student belongs to a class arm, and each arm is assigned its subjects
     * with a teacher against each. The arm is how the school groups them; the
     * subject is what they actually study, so that is what the menu shows.
     */
    public function subjects(): View
    {
        $user = auth()->user();

        $armIds = ClassEnrollment::where('user_id', $user->id)
            ->pluck('class_arm_id')
            ->filter();

        $assignments = ClassSubjectAssignment::with(['subject', 'teacher', 'classArm.classLevel'])
            ->whereIn('class_arm_id', $armIds)
            ->get()
            ->filter(fn ($a) => $a->subject !== null)
            // An arm can appear twice for one subject across a split timetable;
            // the student only needs the subject once.
            ->unique('subject_id')
            ->sortBy(fn ($a) => $a->subject->name)
            ->values();

        return view('student.subjects', compact('assignments'));
    }

    public function subjectShow(Subject $subject): View
    {
        $user = auth()->user();
        $school = $this->school();

        $armIds = ClassEnrollment::where('user_id', $user->id)
            ->pluck('class_arm_id')
            ->filter();

        // Reachable only if one of the student's own arms is taught it.
        $assignment = ClassSubjectAssignment::with(['teacher', 'classArm.classLevel'])
            ->where('subject_id', $subject->id)
            ->whereIn('class_arm_id', $armIds)
            ->first();

        abort_unless($assignment !== null, 404);

        $exams = Exam::where('school_id', $school?->id)
            ->where('subject_id', $subject->id)
            ->where('status', Exam::STATUS_PUBLISHED)
            ->where(function ($q) use ($armIds) {
                $q->whereIn('class_arm_id', $armIds)->orWhereNull('class_arm_id');
            })
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get();

        $attempts = ExamAttempt::where('user_id', $user->id)
            ->where('submitted', true)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        // Only this subject's guides. The shared query keeps the visibility
        // rule in one place rather than restating it per screen.
        $guides = $this->studyGuideQuery($user)
            ->where('subject_id', $subject->id)
            ->withCount(['flashcards', 'questions'])
            ->orderBy('title')
            ->get();

        return view('student.subject-show', compact('subject', 'assignment', 'exams', 'attempts', 'guides'));
    }

    // ── Exams ──
    public function exams(): View
    {
        $user = auth()->user();
        $school = $this->school();
        $classIds = ClassEnrollment::where('user_id', $user->id)->pluck('class_arm_id')->filter();

        $exams = Exam::where('school_id', $school?->id)
            ->where('status', Exam::STATUS_PUBLISHED)
            ->whereIn('class_arm_id', $classIds->isEmpty() ? [null] : $classIds)
            ->orWhere(function ($q) use ($school) {
                $q->where('school_id', $school?->id)->where('status', Exam::STATUS_PUBLISHED)->whereNull('class_arm_id');
            })
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('student.exams', compact('exams'));
    }

    public function startExam(Exam $exam): RedirectResponse
    {
        abort_unless($exam->status === Exam::STATUS_PUBLISHED, 403);

        // The scheduling window, if the teacher set one.
        if ($exam->start_time && $exam->start_time->isFuture()) {
            return back()->withErrors([
                'exam' => 'This exam opens '.$exam->start_time->format('j M Y, g:ia').'.',
            ]);
        }

        if ($exam->end_time && $exam->end_time->isPast()) {
            return back()->withErrors(['exam' => 'This exam has closed.']);
        }

        // Respect max attempts
        $attempts = ExamAttempt::where('exam_id', $exam->id)->where('user_id', auth()->id())->where('submitted', true)->count();
        if ($exam->max_attempts && $attempts >= $exam->max_attempts) {
            return back()->withErrors(['exam' => 'Maximum attempts reached.']);
        }

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => auth()->id(),
            'start_time' => now(),
            'submitted' => false,
            'answers' => [],
        ]);

        return redirect()->route('student.exams.take', [$exam, $attempt]);
    }

    public function takeExam(Exam $exam, ExamAttempt $attempt, ExamPaperService $paper): View
    {
        abort_unless($attempt->user_id === auth()->id() && !$attempt->submitted, 403);

        $questions = $paper->questionsFor($exam, $attempt);

        return view('student.exam-take', compact('exam', 'attempt', 'questions'));
    }

    public function submitExam(Request $request, Exam $exam, ExamAttempt $attempt, ExamPaperService $paper): RedirectResponse
    {
        abort_unless($attempt->user_id === auth()->id() && !$attempt->submitted, 403);

        $questions = $exam->questions()->orderBy('order')->get();
        $answers = [];
        $score = 0;
        $maxScore = 0;

        foreach ($questions as $q) {
            $given = $request->input("q.{$q->id}");
            $given = is_string($given) ? $given : null;

            $isCorrect = $paper->isCorrect($q, $given);

            if ($isCorrect) {
                $score += $q->points ?? 1;
            }

            $maxScore += $q->points ?? 1;

            // Store the answer text, not a position: options may be shuffled
            // per attempt, so an index would not survive review.
            $answers[] = [
                'question_id' => $q->id,
                'given' => $given,
                'correct' => $isCorrect,
            ];
        }

        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0;
        $passed = $percentage >= ($exam->pass_mark ?? 50);

        $attempt->update([
            'submitted' => true,
            'end_time' => now(),
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed,
            'answers' => $answers,
        ]);

        return redirect()->route('student.exams.result', [$exam, $attempt]);
    }

    public function examResult(Exam $exam, ExamAttempt $attempt, ExamPaperService $paper): View
    {
        abort_unless($attempt->user_id === auth()->id(), 403);
        $questions = $exam->questions()->orderBy('order')->get();

        return view('student.exam-result', compact('exam', 'attempt', 'questions', 'paper'));
    }

    public function studyIndex(): View
    {
        $user = auth()->user();

        // Every published guide for the subjects this student is taught, not
        // only the ones that happen to carry flashcards: a guide is worth
        // reading on its own, and hiding it because nobody generated cards for
        // it yet makes teachers' work disappear.
        $materials = $this->studyGuideQuery($user)
            ->withCount(['flashcards', 'questions'])
            ->orderBy('title')
            ->get()
            ->groupBy(fn ($m) => $m->subject?->name ?? 'General');

        $dueCount = self::visibleFlashcardsQuery($user)
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()))
            ->count();

        return view('student.study.index', compact('materials', 'dueCount'));
    }

    /**
     * Published guides a student may open.
     *
     * Scoped to the subjects taught to their own class arms. A guide with no
     * subject is school-wide and stays visible, otherwise general material
     * would vanish from the list entirely.
     */
    private function studyGuideQuery(User $user)
    {
        $armIds = ClassEnrollment::where('user_id', $user->id)
            ->pluck('class_arm_id')
            ->filter();

        $subjectIds = ClassSubjectAssignment::whereIn('class_arm_id', $armIds)
            ->pluck('subject_id')
            ->filter()
            ->unique();

        return Material::with('subject')
            ->where('school_id', $user->currentSchool()?->id)
            ->published()
            ->where(function ($q) use ($subjectIds) {
                $q->whereIn('subject_id', $subjectIds)->orWhereNull('subject_id');
            });
    }

    /**
     * Study hub — tabbed view over a material (Flashcards / Quiz /
     * Edit-questions / Images / Study Guide). Mirrors the original
     * /study/[id] page.
     */
    public function studyHub(Material $material): View
    {
        abort_unless($material->isPublished(), 403);
        $user = auth()->user();
        abort_unless($material->school_id === $user->currentSchool()?->id, 403);

        // Same scope as the list. Being in the school is not enough: a guide
        // for a subject this student is not taught should not open by id.
        abort_unless(
            $this->studyGuideQuery($user)->whereKey($material->id)->exists(),
            404
        );

        $material->load([
            'flashcards' => fn ($q) => $q->orderBy('id'),
            'questions' => fn ($q) => $q->orderBy('id'),
            'studyGuide',
            'images' => fn ($q) => $q->orderBy('id'),
            'subject',
            // The topic graph powers the "what to study next" panel.
            'topic.links.linkedTopic',
            'topic.backlinks.topic',
        ]);

        return view('student.study.hub', compact('material'));
    }

    /**
     * Study mode — run a guided review session over a material (or all due).
     */
    public function studySession(Request $request, ?Material $material = null): View
    {
        $user = auth()->user();

        $query = self::visibleFlashcardsQuery($user)
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()));
        if ($material) {
            $query->where('material_id', $material->id);
        }

        $queue = $query->orderBy('due_date')->pluck('id')->all();
        session(['study_queue' => $queue, 'study_index' => 0, 'study_total' => count($queue)]);

        return $this->renderStudyCard($request);
    }

    /**
     * Record an answer during a study session and advance.
     */
    public function studyAnswer(Request $request, Flashcard $flashcard): RedirectResponse|JsonResponse
    {
        abort_unless($this->canReviewFlashcard($flashcard), 403);
        $data = $request->validate(['quality' => 'required|integer|min:0|max:5']);

        $this->applySm2($flashcard, (int) $data['quality']);

        // The deck posts ratings in the background so the reader never waits
        // on a round trip; only the non-JS path needs a redirect.
        if ($request->expectsJson()) {
            return response()->json([
                'due_date' => $flashcard->due_date?->toIso8601String(),
                'interval' => $flashcard->interval,
            ]);
        }

        $queue = session('study_queue', []);
        $index = (int) session('study_index', 0) + 1;
        session(['study_index' => $index]);

        if ($index >= count($queue)) {
            return redirect()->route('student.study.index')->with('status', 'Study session complete — nice work.');
        }

        return back();
    }

    private function renderStudyCard(Request $request): View
    {
        $queue = session('study_queue', []);
        $index = (int) session('study_index', 0);
        $total = (int) session('study_total', count($queue));

        $flashcard = null;
        if ($index < count($queue)) {
            $flashcard = Flashcard::find($queue[$index]);
        }

        return view('student.study.session', compact('flashcard', 'index', 'total'));
    }

    /**
     * SM-2 spaced-repetition update (shared by review + study flows).
     */
    private function canReviewFlashcard(Flashcard $flashcard): bool
    {
        $user = auth()->user();
        if ($flashcard->user_id === $user->id) {
            return true;
        }
        // teacher-generated card on a published material in the student's school
        return (bool) $flashcard->material()
            ->published()
            ->where('school_id', $user->currentSchool()?->id)
            ->count();
    }

    private function applySm2(Flashcard $flashcard, int $quality): void
    {
        $repetitions = $flashcard->repetitions;
        $ease = $flashcard->ease_factor;
        $interval = $flashcard->interval;

        if ($quality < 3) {
            $repetitions = 0;
            $interval = 1;
        } else {
            $repetitions += 1;
            if ($repetitions === 1) {
                $interval = 1;
            } elseif ($repetitions === 2) {
                $interval = 6;
            } else {
                $interval = (int) round($interval * $ease);
            }
        }

        $ease = $ease + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        if ($ease < 1.3) {
            $ease = 1.3;
        }

        $flashcard->update([
            'ease_factor' => $ease,
            'interval' => $interval,
            'repetitions' => $repetitions,
            'lapses' => $quality < 3 ? $flashcard->lapses + 1 : $flashcard->lapses,
            'due_date' => now()->addDays($interval),
            'last_review' => now(),
            'review_count' => $flashcard->review_count + 1,
        ]);
    }
}
