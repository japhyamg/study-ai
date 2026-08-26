<?php

namespace App\Http\Controllers;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassSubjectAssignment;
use App\Models\ExamAttempt;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use App\Services\People\PersonImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
    public function teachers(Request $request): View
    {
        return $this->people($request, SchoolMember::ROLE_TEACHER, 'Teachers');
    }

    public function students(Request $request): View
    {
        return $this->people($request, SchoolMember::ROLE_STUDENT, 'Students');
    }

    public function administrators(Request $request): View
    {
        return $this->people($request, SchoolMember::ROLE_ADMIN, 'Administrators');
    }

    /**
     * One role's people within this school.
     *
     * Membership carries the role, so the list is driven from school_members
     * rather than users: a user row alone says nothing about which school the
     * person belongs to in what capacity.
     */
    private function people(Request $request, string $role, string $heading): View
    {
        $school = $this->school();
        $search = trim((string) $request->get('search', ''));

        // Joined rather than filtered through whereHas: the list is ordered by
        // person name, and a subquery cannot be sorted on. Selecting only
        // school_members.* keeps the joined columns from overwriting the model.
        $members = SchoolMember::query()
            ->with(['user'])
            ->join('users', 'users.id', '=', 'school_members.user_id')
            ->where('school_members.school_id', $school?->id)
            ->where('school_members.role', $role)
            ->when($search, fn ($q) => $this->searchUser($q, $search))
            ->orderBy('users.name')
            ->select('school_members.*')
            ->paginate(25)
            ->withQueryString();

        return view('admin.people.index', compact('members', 'search', 'role', 'heading'));
    }

    /**
     * One person's record.
     *
     * Route model binding resolves any user id, so membership of the current
     * school is checked explicitly - without it an admin could read a user
     * belonging to another tenant by guessing a uuid.
     */
    public function showUser(User $user): View
    {
        $school = $this->school();
        $membership = $this->membershipFor($user);

        $user->load(['adminProfile', 'teacherProfile', 'studentProfile']);

        // What the person actually does here, which is the reason to open the
        // page at all.
        $classes = collect();
        $subjects = collect();

        if ($membership->role === SchoolMember::ROLE_TEACHER) {
            $subjects = ClassSubjectAssignment::with(['subject', 'classArm.classLevel'])
                ->where('school_id', $school?->id)
                ->where('teacher_id', $user->id)
                ->get();

            $classes = ClassArm::with('classLevel')
                ->where('school_id', $school?->id)
                ->where('form_teacher_id', $user->id)
                ->get();
        }

        if ($membership->role === SchoolMember::ROLE_STUDENT) {
            $classes = $user->enrollments()
                ->with('classArm.classLevel')
                ->get()
                ->pluck('classArm')
                ->filter();
        }

        return view('admin.people.show', compact('user', 'membership', 'classes', 'subjects'));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $school = $this->school();
        $membership = $this->membershipFor($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'role' => ['required', Rule::in([
                SchoolMember::ROLE_ADMIN, SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT,
            ])],
        ]);

        // An admin removing their own admin rights would lock themselves out of
        // the page they are standing on.
        if ($user->id === $request->user()->id && $data['role'] !== SchoolMember::ROLE_ADMIN) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        // Nor should the last admin be demoted, leaving the school unmanageable.
        if ($membership->role === SchoolMember::ROLE_ADMIN && $data['role'] !== SchoolMember::ROLE_ADMIN) {
            $admins = SchoolMember::where('school_id', $school?->id)
                ->where('role', SchoolMember::ROLE_ADMIN)
                ->count();

            if ($admins <= 1) {
                return back()->withErrors(['role' => 'This is the only administrator. Promote someone else first.']);
            }
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $membership->update(['role' => $data['role']]);

        return back()->with('status', 'Details updated.');
    }

    /**
     * Name or email match.
     *
     * `ilike` is Postgres-only and throws on MySQL, which this runs on, so the
     * comparison is lowered on both sides instead.
     */
    private function searchUser($query, string $search): void
    {
        $term = '%'.mb_strtolower($search).'%';

        $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(users.name) LIKE ?', [$term])
              ->orWhereRaw('LOWER(users.email) LIKE ?', [$term]);
        });
    }

    public function createTeacher(): View
    {
        return view('admin.people.create', [
            'role' => SchoolMember::ROLE_TEACHER,
            'heading' => 'Add a teacher',
            'subjects' => Subject::where('school_id', $this->school()?->id)->orderBy('name')->get(),
        ]);
    }

    public function createStudent(): View
    {
        $school = $this->school();

        return view('admin.people.create', [
            'role' => SchoolMember::ROLE_STUDENT,
            'heading' => 'Add a student',
            'classes' => ClassArm::with('classLevel')
                ->where('school_id', $school?->id)
                ->get()
                ->sortBy(fn ($a) => [$a->classLevel?->position ?? 0, $a->name])
                ->values(),
        ]);
    }

    public function createAdministrator(): View
    {
        return view('admin.people.create', [
            'role' => SchoolMember::ROLE_ADMIN,
            'heading' => 'Add an administrator',
        ]);
    }

    /**
     * Create a person and their profile.
     *
     * Each role is added through its own form because the details genuinely
     * differ: a student has an admission number, a guardian and a class, while
     * a teacher has a staff number and a department. One shared form would ask
     * everyone for all of it.
     */
    public function storePerson(Request $request): RedirectResponse
    {
        $school = $this->school();
        $schoolId = $school?->id;

        $role = $request->input('role');

        abort_unless(in_array($role, [
            SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT, SchoolMember::ROLE_ADMIN,
        ], true), 404);

        $isStudent = $role === SchoolMember::ROLE_STUDENT;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            // A student signs in with their admission number, so an email is
            // optional for them and required for everyone else.
            'email' => [
                $isStudent ? 'nullable' : 'required',
                'email', 'max:255',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],

            // Student
            'admission_number' => [
                Rule::requiredIf($isStudent), 'nullable', 'string', 'max:50',
                Rule::unique('student_profiles', 'admission_number')
                    ->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'class_arm_id' => ['nullable', 'exists:class_arms,id'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'guardian_email' => ['nullable', 'email', 'max:255'],

            // Staff
            'staff_number' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
        ]);

        // Given by the admin, or generated so the account is never left with a
        // guessable password. Either way it is shown once on the next screen.
        $password = $data['password'] ?? Str::password(12, symbols: false);

        $user = DB::transaction(function () use ($data, $role, $schoolId, $password, $isStudent) {
            $user = User::create([
                'school_id' => $schoolId,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            SchoolMember::create([
                'user_id' => $user->id,
                'school_id' => $schoolId,
                'role' => $role,
            ]);

            if ($isStudent) {
                $user->studentProfile()->create([
                    'school_id' => $schoolId,
                    'admission_number' => $data['admission_number'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'guardian_name' => $data['guardian_name'] ?? null,
                    'guardian_phone' => $data['guardian_phone'] ?? null,
                    'guardian_email' => $data['guardian_email'] ?? null,
                    'enrolled_on' => now(),
                ]);

                if (! empty($data['class_arm_id'])) {
                    // class_enrollments carries no school_id; the arm supplies it.
                    ClassEnrollment::firstOrCreate([
                        'class_arm_id' => $data['class_arm_id'],
                        'user_id' => $user->id,
                    ], [
                        'role' => SchoolMember::ROLE_STUDENT,
                        'enrolled_at' => now(),
                    ]);
                }
            }

            if ($role === SchoolMember::ROLE_TEACHER) {
                $user->teacherProfile()->create([
                    'school_id' => $schoolId,
                    'staff_number' => $data['staff_number'] ?? null,
                    'department' => $data['department'] ?? null,
                    'qualification' => $data['qualification'] ?? null,
                ]);
            }

            if ($role === SchoolMember::ROLE_ADMIN) {
                $user->adminProfile()->create([
                    'school_id' => $schoolId,
                    'staff_number' => $data['staff_number'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'department' => $data['department'] ?? null,
                ]);
            }

            return $user;
        });

        $listRoute = match ($role) {
            SchoolMember::ROLE_TEACHER => 'admin.teachers',
            SchoolMember::ROLE_STUDENT => 'admin.students',
            default => 'admin.administrators',
        };

        // Flashed rather than emailed: there is no mail configured, and the
        // admin needs to hand these over in person.
        return redirect()->route($listRoute)->with('credentials', [
            'name' => $user->name,
            'login' => $isStudent ? $data['admission_number'] : $data['email'],
            'password' => $password,
        ]);
    }

    public function importForm(string $role): View
    {
        abort_unless(in_array($role, [SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT], true), 404);

        return view('admin.people.import', [
            'role' => $role,
            'heading' => $role === SchoolMember::ROLE_TEACHER ? 'Import teachers' : 'Import students',
            'columns' => PersonImporter::COLUMNS[$role],
            'samples' => PersonImporter::SAMPLES[$role],
        ]);
    }

    /**
     * The blank file to fill in.
     *
     * It ships with two example rows so the expected shape of an admission
     * number or a class name is visible rather than described.
     */
    public function importTemplate(string $role): StreamedResponse
    {
        abort_unless(in_array($role, [SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT], true), 404);

        $columns = PersonImporter::COLUMNS[$role];
        $samples = PersonImporter::SAMPLES[$role];

        return response()->streamDownload(function () use ($columns, $samples) {
            $out = fopen('php://output', 'w');

            // Excel reads a plain UTF-8 CSV as the local codepage and mangles
            // accented names; the BOM makes it read as UTF-8.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $columns);

            foreach ($samples as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $role.'-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importPeople(Request $request, PersonImporter $importer): RedirectResponse
    {
        $role = $request->input('role');

        abort_unless(in_array($role, [SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_STUDENT], true), 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.mimes' => 'Upload a CSV file. Export from Excel with Save As → CSV.',
        ]);

        $school = $this->school();

        abort_unless($school !== null, 404);

        $result = $importer->import($request->file('file')->getRealPath(), $role, $school);

        if ($result['errors'] !== []) {
            return back()->with('import_errors', $result['errors']);
        }

        $listRoute = $role === SchoolMember::ROLE_TEACHER ? 'admin.teachers' : 'admin.students';

        return redirect()->route($listRoute)
            ->with('status', $result['created'].' '.Str::plural('person', $result['created']).' imported.');
    }

    /**
     * Issue a new password for someone.
     *
     * Shown once rather than emailed: there is no mail configured, and an
     * admin resetting a password is usually standing next to the person.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->membershipFor($user);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $password = $data['password'] ?? Str::password(12, symbols: false);

        $user->update(['password' => Hash::make($password)]);

        return back()->with('credentials', [
            'name' => $user->name,
            'login' => $user->email ?: $user->studentProfile?->admission_number,
            'password' => $password,
        ]);
    }

    /**
     * Sign in as one of your own teachers or students.
     *
     * Used to see exactly what they see when something is reported. Admins are
     * excluded as targets: impersonating a peer is a privilege grab, not
     * support, and there is nothing about an admin's view worth reproducing
     * that the acting admin cannot already reach.
     */
    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $membership = $this->membershipFor($user);
        $admin = $request->user();

        if ($user->id === $admin->id) {
            return back()->withErrors(['member' => 'You are already yourself.']);
        }

        if ($membership->role === SchoolMember::ROLE_ADMIN) {
            return back()->withErrors(['member' => 'Administrators cannot be impersonated.']);
        }

        if (! $user->is_active) {
            return back()->withErrors(['member' => 'This account is deactivated.']);
        }

        // Keep the *original* admin if one is already stashed, so a second hop
        // cannot strand someone as a student with no way back.
        $originalId = $request->session()->get('admin_impersonator_id', $admin->id);

        Log::warning('Admin impersonation started', [
            'admin_id' => $originalId,
            'target_user_id' => $user->id,
            'school_id' => $membership->school_id,
            'ip' => $request->ip(),
        ]);

        Auth::guard('web')->login($user);

        // Regenerate before writing the marker: the identity behind the session
        // has changed, and the old id must not remain valid.
        $request->session()->regenerate();
        $request->session()->put('admin_impersonator_id', $originalId);

        $home = $membership->role === SchoolMember::ROLE_TEACHER
            ? 'teacher.dashboard'
            : 'student.dashboard';

        return redirect()->route($home)
            ->with('status', 'You are now signed in as '.$user->name.'.');
    }

    /**
     * Delete a person and everything owned by their account.
     *
     * Distinct from removing them from the school, which keeps the user row.
     */
    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $membership = $this->membershipFor($user);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['member' => 'You cannot delete your own account.']);
        }

        if ($membership->role === SchoolMember::ROLE_ADMIN) {
            $admins = SchoolMember::where('school_id', $membership->school_id)
                ->where('role', SchoolMember::ROLE_ADMIN)
                ->count();

            if ($admins <= 1) {
                return back()->withErrors(['member' => 'This is the only administrator.']);
            }
        }

        $listRoute = match ($membership->role) {
            SchoolMember::ROLE_TEACHER => 'admin.teachers',
            SchoolMember::ROLE_STUDENT => 'admin.students',
            default => 'admin.administrators',
        };

        $user->delete();

        return redirect()->route($listRoute)->with('status', 'Account deleted.');
    }

    /**
     * The membership tying a user to this school, or 404.
     *
     * Route binding resolves any uuid, so every per-user action goes through
     * here rather than trusting the bound model.
     */
    private function membershipFor(User $user): SchoolMember
    {
        $membership = SchoolMember::where('school_id', $this->school()?->id)
            ->where('user_id', $user->id)
            ->first();

        abort_unless($membership !== null, 404);

        return $membership;
    }

    public function removeMember(Request $request, SchoolMember $member): RedirectResponse
    {
        $school = $this->school();

        if ($member->school_id !== $school?->id) {
            abort(403);
        }

        if ($member->user_id === $request->user()->id) {
            return back()->withErrors(['member' => 'You cannot remove your own account.']);
        }

        // The same reasoning as demotion: a school with no administrator left
        // cannot be managed by anyone.
        if ($member->role === SchoolMember::ROLE_ADMIN) {
            $admins = SchoolMember::where('school_id', $school?->id)
                ->where('role', SchoolMember::ROLE_ADMIN)
                ->count();

            if ($admins <= 1) {
                return back()->withErrors(['member' => 'This is the only administrator.']);
            }
        }

        $listRoute = match ($member->role) {
            SchoolMember::ROLE_TEACHER => 'admin.teachers',
            SchoolMember::ROLE_STUDENT => 'admin.students',
            default => 'admin.administrators',
        };

        $member->delete();

        return redirect()->route($listRoute)->with('status', 'Person removed from the school.');
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
