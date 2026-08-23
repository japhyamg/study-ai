<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\PlatformSetting;
use App\Models\Question;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\TeacherTokenLimit;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:super_admin');
    }

    // ── Dashboard / stats ──
    public function dashboard(Request $request): View
    {
        $activeTab = in_array($request->get('tab'), ['overview','analytics','token-usage','token-limits','usage-teachers','ai-providers','schools'])
            ? $request->get('tab') : 'overview';
        $stats = [
            'totalSchools' => School::count(),
            'totalUsers' => User::count(),
            'totalMaterials' => Material::count(),
            'totalExams' => Exam::count(),
            'totalFlashcards' => Flashcard::count(),
        ];

        $recentSchools = School::withCount('members')->orderBy('created_at', 'desc')->limit(10)->get();

        // Analytics tab data
        $analyticsStats = null;
        $signupsTrend = [];
        $topSchools = [];
        if (in_array($activeTab, ['analytics'])) {
            $analyticsStats = [
                'totalSchools' => School::count(),
                'totalUsers' => User::count(),
                'totalTeachers' => SchoolMember::where('role', SchoolMember::ROLE_TEACHER)->count(),
                'totalStudents' => SchoolMember::where('role', SchoolMember::ROLE_STUDENT)->count(),
                'totalMaterials' => Material::count(),
                'totalExams' => Exam::count(),
                'totalFlashcards' => Flashcard::count(),
                'totalQuestions' => Question::count(),
                'totalAttempts' => ExamAttempt::count(),
                'avgScore' => round((float) ExamAttempt::where('submitted', true)->whereNotNull('percentage')->avg('percentage') ?: 0),
                'passRate' => ExamAttempt::where('submitted', true)->where('passed', true)->count() > 0
                    ? round(100 * ExamAttempt::where('submitted', true)->where('passed', true)->count()
                        / max(1, ExamAttempt::where('submitted', true)->count())) : 0,
            ];
            for ($i = 29; $i >= 0; $i--) {
                $d = now()->subDays($i)->startOfDay();
                $signupsTrend[] = ['date' => $d->format('Y-m-d'), 'count' => (int) User::where('created_at', '>=', $d)->where('created_at', '<', $d->copy()->addDay())->count()];
            }
            $topSchools = School::leftJoin('exams', 'exams.school_id', '=', 'schools.id')
                ->leftJoin('exam_attempts', 'exam_attempts.exam_id', '=', 'exams.id')
                ->select('schools.id', 'schools.name', DB::raw('COUNT(exam_attempts.id) as attempts'))
                ->groupBy('schools.id', 'schools.name')->orderByDesc('attempts')->limit(10)->get()
                ->map(fn ($s) => ['schoolId' => $s->id, 'schoolName' => $s->name, 'attempts' => (int) $s->attempts])->all();
        }

        // Token Usage tab data
        $tokenSummary = null;
        $byOperation = collect();
        $byDay = collect();
        if (in_array($activeTab, ['token-usage'])) {
            $days = (int) ($request->get('days', 30));
            $days = in_array($days, [7, 30, 90]) ? $days : 30;
            $cutoff = now()->subDays($days);
            $tq = TokenUsage::where('created_at', '>=', $cutoff);
            $tokenSummary = [
                'totalTokens' => (int) (clone $tq)->sum('total_tokens'),
                'totalCost' => round((float) (clone $tq)->sum('cost'), 4),
                'totalRequests' => (int) (clone $tq)->count(),
                'avgTokensPerRequest' => (int) ((clone $tq)->count() > 0 ? (clone $tq)->sum('total_tokens') / (clone $tq)->count() : 0),
            ];
            $byOperation = (clone $tq)->select('operation', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as count'))->groupBy('operation')->get()->keyBy('operation');
            $byDay = (clone $tq)->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"), DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as count'))->groupBy('date')->orderBy('date')->get()->keyBy('date');
        }

        // Usage & Teachers tab data
        $usageTeachersSummary = null;
        $schoolsData = [];
        if (in_array($activeTab, ['usage-teachers'])) {
            $days = (int) ($request->get('days', 30));
            $days = in_array($days, [7, 30, 90]) ? $days : 30;
            $cutoff = now()->subDays($days);
            $bySchool = TokenUsage::where('created_at', '>=', $cutoff)->select('school_id', DB::raw('SUM(total_tokens) as tokens'))->groupBy('school_id')->pluck('tokens', 'school_id');
            $schoolCosts = TokenUsage::where('created_at', '>=', $cutoff)->select('school_id', DB::raw('SUM(cost) as cost'))->groupBy('school_id')->pluck('cost', 'school_id');
            $schoolReqs = TokenUsage::where('created_at', '>=', $cutoff)->select('school_id', DB::raw('COUNT(*) as requests'))->groupBy('school_id')->pluck('requests', 'school_id');
            $schoolIds = $bySchool->keys()->toArray();
            $schools = School::whereIn('id', $schoolIds)->get()->keyBy('id');
            $usageTeachersSummary = [
                'totalTokens' => TokenUsage::where('created_at', '>=', $cutoff)->sum('total_tokens'),
                'totalCost' => round((float) TokenUsage::where('created_at', '>=', $cutoff)->sum('cost'), 4),
                'totalRequests' => TokenUsage::where('created_at', '>=', $cutoff)->count(),
                'schoolCount' => TokenUsage::where('created_at', '>=', $cutoff)->whereNotNull('school_id')->distinct()->count('school_id'),
            ];
            $teacherMembers = SchoolMember::where('role', SchoolMember::ROLE_TEACHER)->get();
            $seen = []; $teachers = [];
            foreach ($teacherMembers as $m) { if (isset($seen[$m->user_id])) continue; $seen[$m->user_id] = true; $teachers[] = $m; }
            $userIds = array_column($teachers, 'user_id');
            $profiles = User::whereIn('id', $userIds)->get()->keyBy('id');
            $monthStart = now()->startOfMonth();
            $teacherUsage = TokenUsage::whereIn('user_id', $userIds)->where('created_at', '>=', $monthStart)->select('user_id', DB::raw('SUM(total_tokens) as used'))->groupBy('user_id')->pluck('used', 'user_id');
            $schoolTeachers = [];
            foreach ($teachers as $t) {
                $schoolTeachers[$t->school_id][] = ['id' => $t->user_id, 'name' => $profiles[$t->user_id]?->name ?? 'Unnamed', 'email' => $profiles[$t->user_id]?->email ?? '', 'role' => $t->role, 'usedThisMonth' => (int) ($teacherUsage[$t->user_id] ?? 0)];
            }
            foreach ($bySchool as $sid => $tokens) {
                $schoolsData[] = ['schoolId' => $sid, 'schoolName' => $sid ? ($schools[$sid]?->name ?? 'Unknown') : 'No School', 'tokens' => (int) $tokens, 'cost' => round((float) ($schoolCosts[$sid] ?? 0), 4), 'requests' => (int) ($schoolReqs[$sid] ?? 0), 'teachers' => $schoolTeachers[$sid] ?? []];
            }
            usort($schoolsData, fn ($a, $b) => $b['tokens'] <=> $a['tokens']);
        }

        // Token Limits tab data (reuse tokenLimits logic)
        $tokenLimitRows = [];
        $defaultLimit = 1000000;
        if (in_array($activeTab, ['token-limits'])) {
            $defaultLimit = (int) (PlatformSetting::where('key', 'teacher_default_monthly_limit')->value('value') ?? 1000000);
            $teacherLimitMembers = SchoolMember::whereIn('role', [SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_ADMIN])->get();
            $seen2 = []; $tlist = [];
            foreach ($teacherLimitMembers as $m) { if (isset($seen2[$m->user_id])) continue; $seen2[$m->user_id] = true; $tlist[] = $m; }
            $uids = array_column($tlist, 'user_id');
            $sids = array_column($tlist, 'school_id');
            $profiles2 = User::whereIn('id', $uids)->get()->keyBy('id');
            $schools2 = School::whereIn('id', $sids)->get()->keyBy('id');
            $limits = TeacherTokenLimit::whereIn('user_id', $uids)->get()->keyBy('user_id');
            $monthStart = now()->startOfMonth();
            $usage2 = TokenUsage::whereIn('user_id', $uids)->where('created_at', '>=', $monthStart)->select('user_id', DB::raw('SUM(total_tokens) as used'))->groupBy('user_id')->pluck('used', 'user_id');
            foreach ($tlist as $t) {
                $lim = $limits[$t->user_id] ?? null;
                $ml = $lim?->monthly_limit ?? $defaultLimit;
                $used = (int) ($usage2[$t->user_id] ?? 0);
                $tokenLimitRows[] = ['user_id' => $t->user_id, 'name' => $profiles2[$t->user_id]?->name ?? 'Unnamed', 'email' => $profiles2[$t->user_id]?->email ?? '', 'school_name' => $schools2[$t->school_id]?->name ?? 'Unknown', 'role' => $t->role, 'is_enabled' => $lim ? $lim->is_enabled : true, 'monthly_limit' => $ml, 'used_this_month' => $used, 'remaining' => max(0, $ml - $used)];
            }
        }

        // AI Providers tab data
        $providers = null;
        if (in_array($activeTab, ['ai-providers'])) {
            $providers = AiProvider::orderBy('created_at')->get();
        }

        // Schools tab data
        $schoolList = null;
        if (in_array($activeTab, ['schools'])) {
            $schoolList = School::withCount('members')->orderBy('created_at', 'desc')->limit(10)->get();
        }

        return view('super-admin.dashboard', compact(
            'activeTab', 'stats', 'recentSchools',
            'analyticsStats', 'signupsTrend', 'topSchools',
            'tokenSummary', 'byOperation', 'byDay',
            'usageTeachersSummary', 'schoolsData',
            'tokenLimitRows', 'defaultLimit',
            'providers', 'schoolList'
        ));
    }

    // ── Schools ──
    public function schools(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $query = School::withCount('members');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }
        $schools = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('super-admin.schools', compact('schools', 'search'));
    }

    public function storeSchool(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:schools,slug',
            'logo' => 'nullable|string|max:1000',
        ]);

        School::create($data);

        return redirect()->route('super-admin.schools')->with('status', 'School created.');
    }

    public function updateSchool(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:schools,slug,' . $school->id,
            'logo' => 'nullable|string|max:1000',
        ]);

        $school->update($data);

        return redirect()->route('super-admin.schools')->with('status', 'School updated.');
    }

    public function destroySchool(School $school): RedirectResponse
    {
        $school->delete();
        return redirect()->route('super-admin.schools')->with('status', 'School deleted.');
    }

    // ── School detail (members + role management) ──
    public function schoolDetail(School $school): View
    {
        $school->load(['members.user', 'classes', 'materials', 'exams']);
        $memberCount = $school->members->count();
        $materialCount = $school->materials->count();
        $examCount = $school->exams->count();
        $flashcardCount = Flashcard::where('user_id', $school->members->pluck('user_id')->toArray())->count();
        return view('super-admin.school-detail', compact('school', 'memberCount', 'materialCount', 'examCount', 'flashcardCount'));
    }

    public function updateMemberRoleInSchool(Request $request, School $school, SchoolMember $member): RedirectResponse
    {
        if ($member->school_id !== $school->id) {
            abort(404);
        }
        $data = $request->validate([
            'role' => ['required', Rule::in([SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_ADMIN, SchoolMember::ROLE_STUDENT])],
        ]);
        $member->update(['role' => $data['role']]);
        return back()->with('status', 'Member role updated.');
    }

    // ── AI Providers ──
    public function aiProviders(): View
    {
        $providers = AiProvider::orderBy('created_at')->get();
        return view('super-admin.ai-providers', compact('providers'));
    }

    public function storeAiProvider(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'base_url' => 'required|url|max:1000',
            'api_key' => 'required|string|max:2000',
            'model' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $provider = AiProvider::create($data);

        // enforce single active provider
        if ($provider->is_active) {
            AiProvider::where('id', '!=', $provider->id)->update(['is_active' => false]);
        }

        return redirect()->route('super-admin.ai-providers')->with('status', 'Provider saved.');
    }

    public function updateAiProvider(Request $request, AiProvider $provider): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'base_url' => 'required|url|max:1000',
            'api_key' => 'nullable|string|max:2000',
            'model' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($data['api_key'])) {
            unset($data['api_key']); // keep existing
        }
        $data['is_active'] = $request->boolean('is_active');
        $provider->update($data);

        if ($provider->is_active) {
            AiProvider::where('id', '!=', $provider->id)->update(['is_active' => false]);
        }

        return redirect()->route('super-admin.ai-providers')->with('status', 'Provider updated.');
    }

    public function destroyAiProvider(AiProvider $provider): RedirectResponse
    {
        $wasActive = (bool) $provider->is_active;

        $provider->delete();

        // Deleting the active provider would otherwise leave none active, and
        // AiService resolves the provider with where('is_active', true) — so
        // every generation would fail with no obvious cause. Promote the next
        // one rather than leaving the platform silently broken.
        $promoted = null;

        if ($wasActive) {
            $promoted = AiProvider::orderBy('created_at')->first();
            $promoted?->update(['is_active' => true]);
        }

        $message = match (true) {
            $wasActive && $promoted !== null => 'Provider deleted. "'.$promoted->name.'" is now active.',
            $wasActive => 'Provider deleted. No provider is active, so generation is disabled until you add one.',
            default => 'Provider deleted.',
        };

        return redirect()->route('super-admin.ai-providers')->with('status', $message);
    }

    // ── Token limits (teachers) ──
    public function tokenLimits(Request $request): View
    {
        $search = strtolower(trim((string) $request->get('search', '')));

        $teacherMembers = SchoolMember::whereIn('role', [SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_ADMIN])
            ->get();

        $seen = [];
        $teachers = [];
        foreach ($teacherMembers as $m) {
            if (isset($seen[$m->user_id])) {
                continue;
            }
            $seen[$m->user_id] = true;
            $teachers[] = $m;
        }

        $userIds = array_column($teachers, 'user_id');
        $schoolIds = array_column($teachers, 'school_id');

        $profiles = User::whereIn('id', $userIds)->get()->keyBy('id');
        $schools = School::whereIn('id', $schoolIds)->get()->keyBy('id');
        $limits = TeacherTokenLimit::whereIn('user_id', $userIds)->get()->keyBy('user_id');

        $monthStart = now()->startOfMonth();
        $usage = TokenUsage::whereIn('user_id', $userIds)
            ->where('created_at', '>=', $monthStart)
            ->select('user_id', DB::raw('SUM(total_tokens) as used'))
            ->groupBy('user_id')
            ->pluck('used', 'user_id');

        $defaultLimit = (int) (PlatformSetting::where('key', 'teacher_default_monthly_limit')->value('value') ?? 1000000);

        $rows = [];
        foreach ($teachers as $t) {
            $profile = $profiles[$t->user_id] ?? null;
            $limit = $limits[$t->user_id] ?? null;
            $monthlyLimit = $limit?->monthly_limit ?? $defaultLimit;
            $isEnabled = $limit ? $limit->is_enabled : true;
            $used = (int) ($usage[$t->user_id] ?? 0);
            $name = $profile?->name ?? 'Unnamed';
            $email = $profile?->email ?? '';

            if ($search && !str_contains(strtolower($name), $search) && !str_contains(strtolower($email), $search)) {
                continue;
            }

            $rows[] = [
                'user_id' => $t->user_id,
                'name' => $name,
                'email' => $email,
                'school_name' => $schools[$t->school_id]?->name ?? 'Unknown',
                'role' => $t->role,
                'is_enabled' => $isEnabled,
                'monthly_limit' => $monthlyLimit,
                'used_this_month' => $used,
                'remaining' => max(0, $monthlyLimit - $used),
            ];
        }

        return view('super-admin.token-limits', compact('rows', 'defaultLimit', 'search'));
    }

    public function setDefaultTokenLimit(Request $request): RedirectResponse
    {
        $limit = (int) $request->input('default_limit');
        if ($limit <= 0) {
            return back()->withErrors(['default_limit' => 'Must be a positive number.']);
        }
        PlatformSetting::updateOrCreate(
            ['key' => 'teacher_default_monthly_limit'],
            ['value' => (string) $limit]
        );
        return back()->with('status', 'Default limit updated.');
    }

    public function setTeacherTokenLimit(Request $request, string $userId): RedirectResponse
    {
        $data = $request->validate([
            'monthly_limit' => 'nullable|integer|min:1',
            'is_enabled' => 'nullable|boolean',
        ]);

        $default = (int) (PlatformSetting::where('key', 'teacher_default_monthly_limit')->value('value') ?? 1000000);

        TeacherTokenLimit::updateOrCreate(
            ['user_id' => $userId],
            [
                'monthly_limit' => $data['monthly_limit'] ?? $default,
                'is_enabled' => $request->boolean('is_enabled', true),
            ]
        );

        return back()->with('status', 'Teacher limit updated.');
    }

    // ── Analytics (platform-wide stats + trends) ──
    public function analytics(): View
    {
        $stats = [
            'totalSchools' => School::count(),
            'totalUsers' => User::count(),
            'totalTeachers' => SchoolMember::where('role', SchoolMember::ROLE_TEACHER)->count(),
            'totalStudents' => SchoolMember::where('role', SchoolMember::ROLE_STUDENT)->count(),
            'totalMaterials' => Material::count(),
            'totalExams' => Exam::count(),
            'totalFlashcards' => Flashcard::count(),
            'totalQuestions' => Question::count(),
            'totalAttempts' => ExamAttempt::count(),
            'avgScore' => round((float) ExamAttempt::where('submitted', true)->whereNotNull('percentage')->avg('percentage') ?: 0),
            'passRate' => ExamAttempt::where('submitted', true)->where('passed', true)->count() > 0
                ? round(100 * ExamAttempt::where('submitted', true)->where('passed', true)->count()
                    / max(1, ExamAttempt::where('submitted', true)->count())) : 0,
        ];

        // signup trend (last 30 days)
        $signupsTrend = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->startOfDay();
            $cnt = User::where('created_at', '>=', $d)->where('created_at', '<', $d->copy()->addDay())->count();
            $signupsTrend[] = ['date' => $d->format('Y-m-d'), 'count' => $cnt];
        }

        // top schools by exam attempts
        $topSchools = School::leftJoin('exams', 'exams.school_id', '=', 'schools.id')
            ->leftJoin('exam_attempts', 'exam_attempts.exam_id', '=', 'exams.id')
            ->select('schools.id', 'schools.name', DB::raw('COUNT(exam_attempts.id) as attempts'))
            ->groupBy('schools.id', 'schools.name')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get()
            ->map(fn ($s) => ['schoolId' => $s->id, 'schoolName' => $s->name, 'attempts' => (int) $s->attempts])
            ->all();

        return view('super-admin.analytics', compact('stats', 'signupsTrend', 'topSchools'));
    }

    // ── Usage & Teachers (by-school token usage with teacher list) ──
    public function usageTeachers(Request $request): View
    {
        $days = (int) $request->get('days', 30);
        $days = in_array($days, [7, 30, 90]) ? $days : 30;

        $cutoff = now()->subDays($days);

        // aggregate usage by school
        $bySchool = TokenUsage::where('created_at', '>=', $cutoff)
            ->select('school_id', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as requests'))
            ->groupBy('school_id')
            ->pluck('tokens', 'school_id');

        $schoolCosts = TokenUsage::where('created_at', '>=', $cutoff)
            ->select('school_id', DB::raw('SUM(cost) as cost'))
            ->groupBy('school_id')
            ->pluck('cost', 'school_id');

        $schoolReqs = TokenUsage::where('created_at', '>=', $cutoff)
            ->select('school_id', DB::raw('COUNT(*) as requests'))
            ->groupBy('school_id')
            ->pluck('requests', 'school_id');

        // also include users without school_id
        $noSchoolTokens = TokenUsage::where('created_at', '>=', $cutoff)->whereNull('school_id')->sum('total_tokens');
        $noSchoolCost = TokenUsage::where('created_at', '>=', $cutoff)->whereNull('school_id')->sum('cost');
        $noSchoolReqs = TokenUsage::where('created_at', '>=', $cutoff)->whereNull('school_id')->count();

        $summary = [
            'totalTokens' => TokenUsage::where('created_at', '>=', $cutoff)->sum('total_tokens'),
            'totalCost' => round((float) TokenUsage::where('created_at', '>=', $cutoff)->sum('cost'), 4),
            'totalRequests' => TokenUsage::where('created_at', '>=', $cutoff)->count(),
            'schoolCount' => TokenUsage::where('created_at', '>=', $cutoff)
                ->whereNotNull('school_id')
                ->distinct()
                ->count('school_id'),
        ];

        $schoolIds = $bySchool->keys()->toArray();
        $schools = School::whereIn('id', $schoolIds)->get()->keyBy('id');

        // teacher token usage (month-to-date) for expansion
        $teacherMembers = SchoolMember::whereIn('role', [SchoolMember::ROLE_TEACHER])->get();
        $seen = [];
        $teachers = [];
        foreach ($teacherMembers as $m) {
            if (isset($seen[$m->user_id])) continue;
            $seen[$m->user_id] = true;
            $teachers[] = $m;
        }
        $userIds = array_column($teachers, 'user_id');
        $profiles = User::whereIn('id', $userIds)->get()->keyBy('id');
        $monthStart = now()->startOfMonth();
        $teacherUsage = TokenUsage::whereIn('user_id', $userIds)
            ->where('created_at', '>=', $monthStart)
            ->select('user_id', DB::raw('SUM(total_tokens) as used'))
            ->groupBy('user_id')
            ->pluck('used', 'user_id');

        $schoolTeachers = [];
        foreach ($teachers as $t) {
            $schoolTeachers[$t->school_id][] = [
                'id' => $t->user_id,
                'name' => $profiles[$t->user_id]?->name ?? 'Unnamed',
                'email' => $profiles[$t->user_id]?->email ?? '',
                'role' => $t->role,
                'usedThisMonth' => (int) ($teacherUsage[$t->user_id] ?? 0),
            ];
        }

        $schoolsData = [];
        foreach ($bySchool as $sid => $tokens) {
            $schoolsData[] = [
                'schoolId' => $sid,
                'schoolName' => $sid ? ($schools[$sid]?->name ?? 'Unknown') : 'No School',
                'tokens' => (int) $tokens,
                'cost' => round((float) ($schoolCosts[$sid] ?? 0), 4),
                'requests' => (int) ($schoolReqs[$sid] ?? 0),
                'teachers' => $schoolTeachers[$sid] ?? [],
            ];
        }

        usort($schoolsData, fn ($a, $b) => $b['tokens'] <=> $a['tokens']);

        return view('super-admin.usage-teachers', compact('summary', 'schoolsData', 'days', 'noSchoolTokens', 'noSchoolCost', 'noSchoolReqs'));
    }

    // ── Token usage ──
    public function tokenUsage(Request $request): View
    {
        $days = (int) ($request->get('days', 30));
        $days = in_array($days, [7, 30, 90]) ? $days : 30;
        $cutoff = now()->subDays($days);

        $q = TokenUsage::with(['user', 'school'])->where('created_at', '>=', $cutoff);

        $summary = [
            'totalTokens' => (int) (clone $q)->sum('total_tokens'),
            'totalCost' => round((float) (clone $q)->sum('cost'), 4),
            'totalRequests' => (int) (clone $q)->count(),
            'avgTokensPerRequest' => (int) ((clone $q)->count() > 0 ? (clone $q)->sum('total_tokens') / (clone $q)->count() : 0),
        ];

        // by operation
        $byOperation = (clone $q)->select('operation', DB::raw('SUM(total_tokens) as tokens'),
            DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as count'))
            ->groupBy('operation')->get()->keyBy('operation');

        // by school
        $bySchool = (clone $q)->whereNotNull('school_id')
            ->select('school_id', DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as count'))
            ->groupBy('school_id')->pluck('tokens', 'school_id');
        $bySchoolCost = (clone $q)->whereNotNull('school_id')
            ->select('school_id', DB::raw('SUM(cost) as cost'))->groupBy('school_id')->pluck('cost', 'school_id');
        $bySchoolReqs = (clone $q)->whereNotNull('school_id')
            ->select('school_id', DB::raw('COUNT(*) as count'))->groupBy('school_id')->pluck('count', 'school_id');
        $schoolIds = $bySchool->keys()->toArray();
        $schoolNames = School::whereIn('id', $schoolIds)->pluck('name', 'id');

        // by day
        $byDay = (clone $q)->select(DB::raw("TO_CHAR(created_at, 'YYYY-MM-DD') as date"),
            DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'),
            DB::raw('COUNT(*) as count'))->groupBy('date')->orderBy('date')->get()->keyBy('date');

        $usage = $q->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        return view('super-admin.token-usage', compact(
            'usage', 'summary', 'byOperation', 'bySchool',
            'bySchoolCost', 'bySchoolReqs', 'schoolNames',
            'byDay', 'days'
        ));
    }
}
