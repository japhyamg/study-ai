<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\InviteCode;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academic\AcademicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Class arms — the actual groups students belong to — plus enrollment,
 * per-subject teacher assignment and end-of-session promotion.
 */
class ClassArmController extends Controller
{
    public function __construct(private AcademicService $academic) {}

    protected function school(): ?School
    {
        return auth()->user()?->currentSchool();
    }

    public function index(Request $request): View
    {
        $school = $this->school();

        $arms = ClassArm::with(['classLevel', 'formTeacher'])
            ->withCount(['enrollments', 'subjectAssignments'])
            ->where('school_id', $school?->id)
            ->when($request->filled('level'), fn ($q) => $q->where('class_level_id', $request->get('level')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->get('q').'%'))
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->classLevel?->position ?? 0) <=> ($b->classLevel?->position ?? 0),
                fn ($a, $b) => strcmp($a->name, $b->name),
            ])
            ->values();

        return view('admin.classes.index', [
            'arms' => $arms,
            'levels' => ClassLevel::where('school_id', $school?->id)->orderBy('position')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.classes.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $this->school();
        $data = $this->validateArm($request, $school?->id);

        $data['school_id'] = $school?->id;
        $data['academic_session_id'] = $school?->current_session_id;

        $arm = ClassArm::create($data);

        return redirect()
            ->route('admin.classes.show', $arm)
            ->with('status', $arm->fullName().' created.');
    }

    public function show(ClassArm $class): View
    {
        $this->authorizeSchool($class->school_id);

        $class->load([
            'classLevel', 'formTeacher', 'academicSession',
            'enrollments.user', 'subjectAssignments.subject', 'subjectAssignments.teacher',
        ]);

        return view('admin.classes.show', [
            'arm' => $class,
            'matrix' => $this->academic->subjectMatrix($class),
            'teachers' => $this->teachers(),
            'students' => $this->enrollableStudents($class),
            'promotionTarget' => $this->academic->suggestedPromotionTarget($class),
        ]);
    }

    public function edit(ClassArm $class): View
    {
        $this->authorizeSchool($class->school_id);

        return view('admin.classes.edit', array_merge($this->formData(), ['arm' => $class]));
    }

    public function update(Request $request, ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $class->update($this->validateArm($request, $class->school_id, $class));

        return redirect()
            ->route('admin.classes.show', $class)
            ->with('status', 'Class updated.');
    }

    public function destroy(ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        if ($class->enrollments()->exists()) {
            return back()->with('error', 'Move or remove the students in this class before deleting it.');
        }

        $class->delete();

        return redirect()->route('admin.classes.index')->with('status', 'Class deleted.');
    }

    // ── Enrollment ──

    public function enroll(Request $request, ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        try {
            $this->academic->enroll($class, User::findOrFail($data['user_id']));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Student enrolled.');
    }

    public function unenroll(ClassArm $class, string $userId): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $class->enrollments()->where('user_id', $userId)->delete();

        return back()->with('status', 'Student removed from this class.');
    }

    // ── Subject ↔ teacher ──

    public function assignSubject(Request $request, ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $data = $request->validate([
            'subject_id' => ['required', Rule::exists('subjects', 'id')->where('school_id', $class->school_id)],
            'teacher_id' => ['required', 'exists:users,id'],
        ]);

        try {
            $this->academic->assignTeacher(
                $class,
                Subject::findOrFail($data['subject_id']),
                User::findOrFail($data['teacher_id'])
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Teacher assigned.');
    }

    public function unassignSubject(Request $request, ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $data = $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $this->academic->unassignTeacher($class, Subject::findOrFail($data['subject_id']));

        return back()->with('status', 'Teacher unassigned.');
    }

    // ── Promotion ──

    public function promote(Request $request, ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $data = $request->validate([
            'target_arm_id' => ['required', Rule::exists('class_arms', 'id')->where('school_id', $class->school_id)],
        ]);

        $target = ClassArm::findOrFail($data['target_arm_id']);

        if ($target->id === $class->id) {
            return back()->with('error', 'Choose a different class to promote into.');
        }

        $result = $this->academic->promoteArm($class, $target);

        $message = $result['promoted'].' student(s) promoted to '.$target->fullName().'.';

        if (! empty($result['skipped'])) {
            $message .= ' Skipped: '.implode('; ', $result['skipped']);
        }

        return back()->with('status', $message);
    }

    // ── Invite codes ──

    public function inviteCodes(ClassArm $class): View
    {
        $this->authorizeSchool($class->school_id);

        return view('admin.classes.invite-codes', [
            'arm' => $class,
            'codes' => InviteCode::where('class_arm_id', $class->id)->latest()->get(),
        ]);
    }

    public function storeInviteCode(Request $request, ClassArm $class): RedirectResponse
    {
        $this->authorizeSchool($class->school_id);

        $data = $request->validate([
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        InviteCode::create([
            'school_id' => $class->school_id,
            'class_arm_id' => $class->id,
            'code' => strtoupper(bin2hex(random_bytes(4))),
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return back()->with('status', 'Invite code generated.');
    }

    // ── helpers ──

    private function validateArm(Request $request, ?string $schoolId, ?ClassArm $existing = null): array
    {
        $unique = Rule::unique('class_arms')
            ->where(fn ($q) => $q->where('class_level_id', $request->get('class_level_id')));

        if ($existing) {
            $unique->ignore($existing->id);
        }

        return $request->validate([
            'class_level_id' => ['required', Rule::exists('class_levels', 'id')->where('school_id', $schoolId)],
            'name' => ['required', 'string', 'max:40', $unique],
            'stream' => ['nullable', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'form_teacher_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'That class already exists at this level.',
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $school = $this->school();

        return [
            'levels' => ClassLevel::where('school_id', $school?->id)->orderBy('position')->get(),
            'teachers' => $this->teachers(),
            'streams' => config('academic.presets.'.config('academic.preset').'.streams', []),
        ];
    }

    private function teachers()
    {
        return User::whereHas('memberships', fn ($q) => $q
            ->where('school_id', $this->school()?->id)
            ->whereIn('role', [SchoolMember::ROLE_TEACHER, SchoolMember::ROLE_ADMIN]))
            ->orderBy('name')
            ->get();
    }

    /** Students of this school who are not already in this arm. */
    private function enrollableStudents(ClassArm $arm)
    {
        return User::whereHas('memberships', fn ($q) => $q
            ->where('school_id', $arm->school_id)
            ->where('role', SchoolMember::ROLE_STUDENT))
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('class_arm_id', $arm->id))
            ->orderBy('name')
            ->get();
    }

    private function authorizeSchool(?string $schoolId): void
    {
        abort_unless($schoolId && $schoolId === $this->school()?->id, 403);
    }
}
