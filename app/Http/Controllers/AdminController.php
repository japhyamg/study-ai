<?php

namespace App\Http\Controllers;

use App\Models\ClassArm;
use App\Models\ExamAttempt;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
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
            'classes' => ClassArm::where('school_id', $schoolId)->count(),
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
        $classStats = ClassArm::with('classLevel')->where('school_id', $schoolId)
            ->withCount('enrollments')
            ->with(['exams' => fn ($q) => $q->withCount('attempts')])
            ->get()
            ->map(function ($c) {
                $attempts = ExamAttempt::whereHas('exam', fn ($q) => $q->where('class_arm_id', $c->id))->get();
                return [
                    'name' => $c->fullName(),
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
        $levels = \App\Models\ClassLevel::where('school_id', $school?->id)->orderBy('position')->get();

        return view('admin.subjects.index', compact('subjects', 'levels'));
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'category' => 'nullable|in:core,elective,vocational',
            'applies_to' => 'nullable|array',
            'applies_to.*' => 'string|max:20',
            'description' => 'nullable|string|max:500',
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
            'category' => 'nullable|in:core,elective,vocational',
            'applies_to' => 'nullable|array',
            'applies_to.*' => 'string|max:20',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
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

}
