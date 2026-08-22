<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\School;
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

        $enrollments = ClassEnrollment::with('class.subject')
            ->where('user_id', $user->id)
            ->get();
        $classIds = $enrollments->pluck('class_id')->filter();

        $availableExams = Exam::where('school_id', $school?->id)
            ->where('status', Exam::STATUS_PUBLISHED)
            ->whereIn('class_id', $classIds->isEmpty() ? [null] : $classIds)
            ->orWhere(function ($q) use ($school) {
                $q->where('school_id', $school?->id)->where('status', Exam::STATUS_PUBLISHED)->whereNull('class_id');
            })
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->get();

        // Upcoming exams (next 3 with a future/present start time, else latest published)
        $upcomingExams = (clone $availableExams)
            ->filter(fn ($e) => !$e->start_time || $e->start_time->isFuture())
            ->sortBy('start_time')
            ->take(3)
            ->values();

        // Published teacher materials visible to this student (recent first)
        $publishedMaterials = Material::with(['subject'])
            ->where('school_id', $school?->id)
            ->where('published', true)
            ->whereIn('class_id', $classIds->isEmpty() ? [null] : $classIds)
            ->orWhere(function ($q) use ($school) {
                $q->where('school_id', $school?->id)->where('published', true)->whereNull('class_id');
            })
            ->withCount(['flashcards', 'questions'])
            ->orderBy('published_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        $visibleFlashcards = $this->visibleFlashcardsQuery($user);

        $stats = [
            'classes' => $enrollments->count(),
            'dueFlashcards' => (clone $visibleFlashcards)
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()))
                ->count(),
            'upcomingExams' => $upcomingExams->count(),
            'materials' => $publishedMaterials->count(),
        ];

        $dueFlashcards = $stats['dueFlashcards'];

        $recentAttempts = ExamAttempt::with('exam')
            ->where('user_id', $user->id)
            ->where('submitted', true)
            ->orderBy('end_time', 'desc')
            ->limit(5)
            ->get();

        return view('student.dashboard', compact(
            'enrollments', 'availableExams', 'upcomingExams', 'publishedMaterials',
            'stats', 'dueFlashcards', 'recentAttempts'
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
                  ->orWhereHas('material', fn ($m) => $m->where('published', true)->where('school_id', $sid));
            })
            // hide template cards the student already has a personal copy of
            ->whereRaw('not exists (select 1 from flashcards mine where mine.user_id = ? and mine.material_id = flashcards.material_id and mine.front = flashcards.front)', [$user->id]);
    }

    // ── Classes & Materials ──
    public function classes(): View
    {
        $user = auth()->user();
        $enrollments = ClassEnrollment::with(['class' => fn ($q) => $q->withCount('enrollments')])
            ->where('user_id', $user->id)
            ->get();
        return view('student.classes', compact('enrollments'));
    }

    public function classShow(ClassEnrollment $enrollment): View
    {
        abort_unless($enrollment->user_id === auth()->id(), 403);
        $enrollment->load(['class.materials' => fn ($q) => $q->where('published', true)]);
        return view('student.class-show', compact('enrollment'));
    }

    public function materials(): View
    {
        $user = auth()->user();
        $school = $this->school();
        $classIds = ClassEnrollment::where('user_id', $user->id)->pluck('class_id')->filter();
        $materials = Material::with('classRoom')
            ->where('published', true)
            ->whereIn('class_id', $classIds->isEmpty() ? [null] : $classIds)
            ->orWhere(function ($q) use ($school) {
                $q->where('school_id', $school?->id)->where('published', true)->whereNull('class_id');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        return view('student.materials', compact('materials'));
    }

    // ── Exams ──
    public function exams(): View
    {
        $user = auth()->user();
        $school = $this->school();
        $classIds = ClassEnrollment::where('user_id', $user->id)->pluck('class_id')->filter();

        $exams = Exam::where('school_id', $school?->id)
            ->where('status', Exam::STATUS_PUBLISHED)
            ->whereIn('class_id', $classIds->isEmpty() ? [null] : $classIds)
            ->orWhere(function ($q) use ($school) {
                $q->where('school_id', $school?->id)->where('status', Exam::STATUS_PUBLISHED)->whereNull('class_id');
            })
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('student.exams', compact('exams'));
    }

    public function startExam(Exam $exam): RedirectResponse
    {
        abort_unless($exam->status === Exam::STATUS_PUBLISHED, 403);

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

    public function takeExam(Exam $exam, ExamAttempt $attempt): View
    {
        abort_unless($attempt->user_id === auth()->id() && !$attempt->submitted, 403);
        $questions = $exam->questions()->orderBy('order')->get();
        return view('student.exam-take', compact('exam', 'attempt', 'questions'));
    }

    public function submitExam(Request $request, Exam $exam, ExamAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->user_id === auth()->id() && !$attempt->submitted, 403);

        $questions = $exam->questions()->orderBy('order')->get();
        $answers = [];
        $score = 0;
        $maxScore = 0;

        foreach ($questions as $q) {
            $given = $request->input("q.{$q->id}");
            $isCorrect = false;
            if ($given !== null && (string) $given === (string) $q->answer) {
                $isCorrect = true;
                $score += $q->points ?? 1;
            }
            $maxScore += $q->points ?? 1;
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

    public function examResult(Exam $exam, ExamAttempt $attempt): View
    {
        abort_unless($attempt->user_id === auth()->id(), 403);
        $questions = $exam->questions()->orderBy('order')->get();
        return view('student.exam-result', compact('exam', 'attempt', 'questions'));
    }

    // ── Flashcards (SRS) ──
    public function flashcards(Request $request): View
    {
        $user = auth()->user();
        $query = self::visibleFlashcardsQuery($user);
        if ($request->get('view') === 'due') {
            $query->where(function ($q) {
                $q->whereNull('due_date')->orWhere('due_date', '<=', now());
            });
        }
        $flashcards = $query->with('material')->orderBy('due_date')->paginate(20);
        return view('student.flashcards', compact('flashcards'));
    }

    public function reviewFlashcard(Request $request, Flashcard $flashcard): RedirectResponse
    {
        abort_unless($this->canReviewFlashcard($flashcard), 403);
        $data = $request->validate([
            'quality' => 'required|integer|min:0|max:5',
        ]);

        // SM-2 algorithm
        $quality = (int) $data['quality'];
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

        return back();
    }

    /**
     * Study mode — choose a set to review.
     */
    public function studyIndex(): View
    {
        $user = auth()->user();
        $materials = Material::where('school_id', $user->currentSchool()?->id)
            ->where('published', true)
            ->withCount(['flashcards'])
            ->orderBy('title')
            ->get()
            ->filter(fn ($m) => $m->flashcards_count > 0)
            ->values();

        $dueCount = self::visibleFlashcardsQuery($user)
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '<=', now()))
            ->count();

        return view('student.study.index', compact('materials', 'dueCount'));
    }

    /**
     * Study hub — tabbed view over a material (Flashcards / Quiz /
     * Edit-questions / Images / Study Guide). Mirrors the original
     * /study/[id] page.
     */
    public function studyHub(Material $material): View
    {
        abort_unless($material->published, 403);
        $user = auth()->user();
        abort_unless($material->school_id === $user->currentSchool()?->id, 403);

        $material->load([
            'flashcards' => fn ($q) => $q->orderBy('id'),
            'questions' => fn ($q) => $q->orderBy('id'),
            'studyGuide',
            'images' => fn ($q) => $q->orderBy('id'),
            'subject',
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
    public function studyAnswer(Request $request, Flashcard $flashcard): RedirectResponse
    {
        abort_unless($this->canReviewFlashcard($flashcard), 403);
        $data = $request->validate(['quality' => 'required|integer|min:0|max:5']);

        $this->applySm2($flashcard, (int) $data['quality']);

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
            ->where('published', true)
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
