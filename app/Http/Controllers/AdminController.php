<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassModel;
use App\Models\ExamAttempt;
use App\Models\InviteCode;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,super_admin');
    }

    protected function school(): ?School
    {
        return auth()->user()?->currentSchool();
    }

    // ── Dashboard ──
    public function dashboard(): View
    {
        $school = $this->school();
        $schoolId = $school?->id;

        $stats = [
            'classes' => ClassModel::where('school_id', $schoolId)->count(),
            'students' => SchoolMember::where('school_id', $schoolId)->where('role', SchoolMember::ROLE_STUDENT)->count(),
            'exams' => \App\Models\Exam::where('school_id', $schoolId)->count(),
            'avgScore' => round((float) \App\Models\ExamAttempt::whereHas('exam', fn ($q) => $q->where('school_id', $schoolId))
                ->where('submitted', true)->whereNotNull('percentage')->avg('percentage') ?: 0),
        ];

        // Recent activity: students joining classes, new exams, new questions
        $recentActivity = collect();
        $memberJoins = SchoolMember::where('school_id', $schoolId)->orderBy('created_at', 'desc')->limit(5)->get();
        foreach ($memberJoins as $m) {
            $recentActivity->push([
                'type' => 'join',
                'user' => $m->user?->name ?? 'New member',
                'time' => $m->created_at->diffForHumans(),
            ]);
        }
        $recentExamsTmp = \App\Models\Exam::where('school_id', $schoolId)->orderBy('created_at', 'desc')->limit(5)->get();
        foreach ($recentExamsTmp as $e) {
            $recentActivity->push([
                'type' => 'exam',
                'text' => "New exam: {$e->title}",
                'time' => $e->created_at->diffForHumans(),
            ]);
        }
        $recentActivity = $recentActivity->sortByDesc('time')->take(10);

        // Recent exams table
        $recentExamsTable = \App\Models\Exam::where('school_id', $schoolId)
            ->withCount('attempts')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'school', 'recentActivity', 'recentExamsTable'));
    }

    // ── Analytics ──
    public function analytics(): View
    {
        $school = $this->school();
        $schoolId = $school?->id;

        $examAttempts = ExamAttempt::whereHas('exam', fn ($q) => $q->where('school_id', $schoolId))->get();

        $totalAttempts = $examAttempts->count();
        $avgScore = $totalAttempts ? round($examAttempts->avg('percentage'), 1) : 0;
        $passRate = $totalAttempts ? round($examAttempts->where('passed', true)->count() / $totalAttempts * 100) : 0;

        // Score distribution buckets
        $buckets = [0, 0, 0, 0, 0];
        foreach ($examAttempts as $a) {
            $p = $a->percentage ?? 0;
            if ($p < 20) $buckets[0]++;
            elseif ($p < 40) $buckets[1]++;
            elseif ($p < 60) $buckets[2]++;
            elseif ($p < 80) $buckets[3]++;
            else $buckets[4]++;
        }
        $maxBucket = max(1, ...$buckets);

        // Per-class performance
        $classStats = ClassModel::where('school_id', $schoolId)
            ->withCount('enrollments')
            ->with(['exams' => fn ($q) => $q->withCount('attempts')])
            ->get()
            ->map(function ($c) {
                $attempts = ExamAttempt::whereHas('exam', fn ($q) => $q->where('class_id', $c->id))->get();
                return [
                    'name' => $c->name,
                    'students' => $c->enrollments_count,
                    'exams' => $c->exams->count(),
                    'attempts' => $attempts->count(),
                    'avg' => $attempts->count() ? round($attempts->avg('percentage'), 1) : null,
                ];
            });

        $tokenUsage = \App\Models\TokenUsage::where('school_id', $schoolId)
            ->sum('total_tokens');

        return view('admin.analytics', compact(
            'totalAttempts', 'avgScore', 'passRate', 'buckets', 'maxBucket', 'classStats', 'tokenUsage'
        ));
    }

    // ── Classes ──
    public function classes(Request $request): View
    {
        $school = $this->school();
        $classes = ClassModel::with(['subject', 'teacher', 'term'])
            ->where('school_id', $school?->id)
            ->orderBy('name')
            ->paginate(20);

        return view('admin.classes.index', compact('classes'));
    }

    public function createClass(): View
    {
        $school = $this->school();
        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();
        $terms = Term::where('school_id', $school?->id)->orderBy('name')->get();
        $teachers = SchoolMember::with('user')
            ->where('school_id', $school?->id)
            ->where('role', SchoolMember::ROLE_TEACHER)
            ->get();
        return view('admin.classes.create', compact('subjects', 'terms', 'teachers'));
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject_id' => 'nullable|exists:subjects,id',
            'term_id' => 'nullable|exists:terms,id',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        $data['school_id'] = $school?->id;
        ClassModel::create($data);

        return redirect()->route('admin.classes.index')->with('status', 'Class created.');
    }

    public function showClass(ClassModel $class): View
    {
        $this->authorize('view', $class);
        $class->load(['subject', 'teacher', 'term', 'enrollments.user', 'materials', 'exams']);
        return view('admin.classes.show', compact('class'));
    }

    public function editClass(ClassModel $class): View
    {
        $this->authorize('update', $class);
        $school = $this->school();
        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->get();
        $terms = Term::where('school_id', $school?->id)->orderBy('name')->get();
        $teachers = SchoolMember::with('user')
            ->where('school_id', $school?->id)
            ->where('role', SchoolMember::ROLE_TEACHER)
            ->get();
        return view('admin.classes.edit', compact('class', 'subjects', 'terms', 'teachers'));
    }

    public function updateClass(Request $request, ClassModel $class): RedirectResponse
    {
        $this->authorize('update', $class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'subject_id' => 'nullable|exists:subjects,id',
            'term_id' => 'nullable|exists:terms,id',
            'teacher_id' => 'nullable|exists:users,id',
        ]);
        $class->update($data);
        return redirect()->route('admin.classes.show', $class)->with('status', 'Class updated.');
    }

    public function destroyClass(ClassModel $class): RedirectResponse
    {
        $this->authorize('delete', $class);
        $class->delete();
        return redirect()->route('admin.classes.index')->with('status', 'Class deleted.');
    }

    public function assignTeacher(Request $request, ClassModel $class): RedirectResponse
    {
        $this->authorize('update', $class);
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
        ]);
        $class->update(['teacher_id' => $data['teacher_id']]);
        return back()->with('status', 'Teacher assigned.');
    }

    public function enrollStudent(Request $request, ClassModel $class): RedirectResponse
    {
        $this->authorize('update', $class);
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);
        ClassEnrollment::updateOrCreate(
            ['class_id' => $class->id, 'user_id' => $data['user_id']],
            ['role' => SchoolMember::ROLE_STUDENT, 'enrolled_at' => now()]
        );
        return back()->with('status', 'Student enrolled.');
    }

    public function unenrollStudent(ClassModel $class, string $userId): RedirectResponse
    {
        $this->authorize('update', $class);
        ClassEnrollment::where('class_id', $class->id)->where('user_id', $userId)->delete();
        return back()->with('status', 'Student unenrolled.');
    }

    public function inviteCodes(ClassModel $class): View
    {
        $this->authorize('view', $class);
        $codes = InviteCode::where('class_id', $class->id)->orderBy('created_at', 'desc')->get();
        return view('admin.classes.invite-codes', compact('class', 'codes'));
    }

    public function storeInviteCode(Request $request, ClassModel $class): RedirectResponse
    {
        $this->authorize('update', $class);
        $data = $request->validate([
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);
        InviteCode::create([
            'school_id' => $class->school_id,
            'class_id' => $class->id,
            'code' => strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8)),
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
        return back()->with('status', 'Invite code generated.');
    }

    // ── Members ──
    public function members(Request $request): View
    {
        $school = $this->school();
        $search = trim((string) $request->get('search', ''));
        $query = SchoolMember::with(['user'])
            ->where('school_id', $school?->id);
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }
        $members = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();
        return view('admin.members.index', compact('members', 'search'));
    }

    public function inviteMember(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'role' => ['required', Rule::in([SchoolMember::ROLE_ADMIN, SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT])],
        ]);

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'] ?? explode('@', $data['email'])[0],
                'password' => Hash::make(substr(md5(uniqid((string) mt_rand(), true)), 0, 12)),
            ]
        );

        SchoolMember::updateOrCreate(
            ['user_id' => $user->id, 'school_id' => $school?->id],
            ['role' => $data['role']]
        );

        return back()->with('status', 'Member invited.');
    }

    public function bulkInviteMembers(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'emails' => 'required|string',
            'role' => ['required', Rule::in([SchoolMember::ROLE_ADMIN, SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT])],
        ]);

        $emails = array_filter(array_map('trim', preg_split('/[\s,;]+/', $data['emails'])));
        $count = 0;
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => explode('@', $email)[0],
                    'password' => Hash::make(substr(md5(uniqid((string) mt_rand(), true)), 0, 12)),
                ]
            );
            SchoolMember::updateOrCreate(
                ['user_id' => $user->id, 'school_id' => $school?->id],
                ['role' => $data['role']]
            );
            $count++;
        }

        return back()->with('status', "{$count} members invited.");
    }

    public function removeMember(SchoolMember $member): RedirectResponse
    {
        $school = $this->school();
        if ($member->school_id !== $school?->id) {
            abort(403);
        }
        $member->delete();
        return back()->with('status', 'Member removed.');
    }

    public function updateMemberRole(Request $request, SchoolMember $member): RedirectResponse
    {
        $school = $this->school();
        if ($member->school_id !== $school?->id) {
            abort(403);
        }
        $data = $request->validate([
            'role' => ['required', Rule::in([SchoolMember::ROLE_ADMIN, SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT])],
        ]);
        $member->update(['role' => $data['role']]);
        return back()->with('status', 'Role updated.');
    }

    // ── Settings ──
    public function settings(): View
    {
        $school = $this->school();
        return view('admin.settings', compact('school'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string|max:1000',
        ]);
        $school->update($data);
        return back()->with('status', 'Settings saved.');
    }

    // ── Subjects & Terms ──
    public function subjects(): View
    {
        $school = $this->school();
        $subjects = Subject::where('school_id', $school?->id)->orderBy('name')->paginate(30);
        return view('admin.subjects.index', compact('subjects'));
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);
        Subject::create([
            'school_id' => $school?->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);
        return back()->with('status', 'Subject added.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $school = $this->school();
        if ($subject->school_id !== $school?->id) {
            abort(403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
        ]);
        $subject->update($data);
        return back()->with('status', 'Subject updated.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        $school = $this->school();
        if ($subject->school_id !== $school?->id) {
            abort(403);
        }
        $subject->delete();
        return back()->with('status', 'Subject removed.');
    }

    public function terms(): View
    {
        $school = $this->school();
        $terms = Term::where('school_id', $school?->id)->orderBy('name')->paginate(30);
        return view('admin.terms.index', compact('terms'));
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'active' => 'nullable|boolean',
        ]);
        if (!empty($data['active'])) {
            Term::where('school_id', $school?->id)->update(['active' => false]);
        }
        Term::create([
            'school_id' => $school?->id,
            'name' => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'active' => !empty($data['active']),
        ]);
        return back()->with('status', 'Term added.');
    }

    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $school = $this->school();
        if ($term->school_id !== $school?->id) {
            abort(403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'active' => 'nullable|boolean',
        ]);
        if (!empty($data['active'])) {
            Term::where('school_id', $school?->id)->where('id', '!=', $term->id)->update(['active' => false]);
        }
        $term->update([
            'name' => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'active' => !empty($data['active']),
        ]);
        return back()->with('status', 'Term updated.');
    }

    public function destroyTerm(Term $term): RedirectResponse
    {
        $school = $this->school();
        if ($term->school_id !== $school?->id) {
            abort(403);
        }
        $term->delete();
        return back()->with('status', 'Term removed.');
    }
}
