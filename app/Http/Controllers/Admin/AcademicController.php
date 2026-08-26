<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AssessmentType;
use App\Models\ClassLevel;
use App\Models\School;
use App\Models\Term;
use App\Services\Academic\AcademicService;
use App\Services\Academic\SchoolBootstrapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Academic calendar and structure: sessions, terms, class levels and
 * assessment types.
 *
 * Class arms and subject↔teacher assignments live in {@see ClassArmController}.
 */
class AcademicController extends Controller
{
    public function __construct(private AcademicService $academic) {}

    protected function school(): ?School
    {
        return auth()->user()?->currentSchool();
    }

    /** Overview: structure health at a glance. */
    public function index(): View
    {
        $school = $this->school();

        return view('admin.academic.index', [
            'school' => $school,
            'summary' => $this->academic->summary($school),
            'currentSession' => $school?->currentSession,
            'currentTerm' => $school?->currentTerm,
            'sessions' => AcademicSession::where('school_id', $school?->id)
                ->withCount('terms')
                ->orderByDesc('is_current')
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    /** Seed a school that has no structure yet from the configured preset. */
    public function bootstrap(Request $request, SchoolBootstrapper $bootstrapper): RedirectResponse
    {
        $school = $this->school();
        abort_unless($school, 403);

        $preset = $request->validate([
            'preset' => ['nullable', Rule::in(array_keys(config('academic.presets', [])))],
        ])['preset'] ?? null;

        $bootstrapper->bootstrap($school, $preset);

        return back()->with('status', 'Academic structure created. Adjust anything that does not fit your school.');
    }

    // ───────────────────────── Sessions ─────────────────────────

    public function storeSession(Request $request): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('academic_sessions')->where('school_id', $school?->id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $session = AcademicSession::create([
            'school_id' => $school?->id,
            'name' => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]);

        if ($request->boolean('is_current')) {
            $session->makeCurrent();
        }

        return back()->with('status', 'Session added.');
    }

    public function updateSession(Request $request, AcademicSession $session): RedirectResponse
    {
        $this->authorizeSchool($session->school_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60',
                Rule::unique('academic_sessions')->where('school_id', $session->school_id)->ignore($session->id)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $session->update($data);

        return back()->with('status', 'Session updated.');
    }

    public function activateSession(AcademicSession $session): RedirectResponse
    {
        $this->authorizeSchool($session->school_id);
        $session->makeCurrent();

        return back()->with('status', $session->name.' is now the current session.');
    }

    public function destroySession(AcademicSession $session): RedirectResponse
    {
        $this->authorizeSchool($session->school_id);

        if ($session->is_current) {
            return back()->with('error', 'You cannot delete the current session. Make another session current first.');
        }

        $session->delete();

        return back()->with('status', 'Session removed.');
    }

    // ───────────────────────── Terms ─────────────────────────

    public function terms(): View
    {
        $school = $this->school();

        return view('admin.academic.terms', [
            'sessions' => AcademicSession::where('school_id', $school?->id)
                ->with(['terms' => fn ($q) => $q->orderBy('sequence')])
                ->orderByDesc('is_current')
                ->get(),
            'currentTerm' => $school?->currentTerm,
        ]);
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'academic_session_id' => ['required', Rule::exists('academic_sessions', 'id')->where('school_id', $school?->id)],
            'name' => ['required', 'string', 'max:60'],
            'sequence' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'resumption_date' => ['nullable', 'date'],
            'is_current' => ['nullable', 'boolean'],
        ]);

        $nextSequence = Term::where('academic_session_id', $data['academic_session_id'])->max('sequence') + 1;

        $term = Term::create([
            'school_id' => $school?->id,
            'academic_session_id' => $data['academic_session_id'],
            'name' => $data['name'],
            'sequence' => $data['sequence'] ?? $nextSequence,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'resumption_date' => $data['resumption_date'] ?? null,
        ]);

        if ($request->boolean('is_current')) {
            $term->makeCurrent();
        }

        return back()->with('status', 'Term added.');
    }

    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $this->authorizeSchool($term->school_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'sequence' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'resumption_date' => ['nullable', 'date'],
        ]);

        $term->update($data);

        return back()->with('status', 'Term updated.');
    }

    public function activateTerm(Term $term): RedirectResponse
    {
        $this->authorizeSchool($term->school_id);
        $term->makeCurrent();

        // Keep the session pointer consistent with the term.
        if ($term->academicSession && ! $term->academicSession->is_current) {
            $term->academicSession->makeCurrent();
        }

        return back()->with('status', $term->displayName().' is now the current term.');
    }

    public function destroyTerm(Term $term): RedirectResponse
    {
        $this->authorizeSchool($term->school_id);

        if ($term->is_current) {
            return back()->with('error', 'You cannot delete the current term.');
        }

        $term->delete();

        return back()->with('status', 'Term removed.');
    }

    // ───────────────────────── Class levels ─────────────────────────

    public function levels(): View
    {
        $school = $this->school();

        return view('admin.academic.levels', [
            'levels' => ClassLevel::where('school_id', $school?->id)
                ->withCount('arms')
                ->orderBy('position')
                ->get(),
        ]);
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('class_levels')->where('school_id', $school?->id)],
            'stage' => ['nullable', 'string', 'max:40'],
            'position' => ['nullable', 'integer', 'min:0', 'max:99'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['school_id'] = $school?->id;
        $data['code'] = strtolower($data['code']);
        $data['position'] ??= (ClassLevel::where('school_id', $school?->id)->max('position') ?? 0) + 1;

        ClassLevel::create($data);

        return back()->with('status', 'Class level added.');
    }

    public function updateLevel(Request $request, ClassLevel $level): RedirectResponse
    {
        $this->authorizeSchool($level->school_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('class_levels')->where('school_id', $level->school_id)->ignore($level->id)],
            'stage' => ['nullable', 'string', 'max:40'],
            'position' => ['nullable', 'integer', 'min:0', 'max:99'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['code'] = strtolower($data['code']);
        $level->update($data);

        return back()->with('status', 'Class level updated.');
    }

    public function destroyLevel(ClassLevel $level): RedirectResponse
    {
        $this->authorizeSchool($level->school_id);

        if ($level->arms()->exists()) {
            return back()->with('error', 'Remove the classes in this level before deleting it.');
        }

        $level->delete();

        return back()->with('status', 'Class level removed.');
    }

    // ───────────────────────── Assessment types ─────────────────────────

    public function assessmentTypes(): View
    {
        $school = $this->school();

        return view('admin.academic.assessment-types', [
            'types' => AssessmentType::where('school_id', $school?->id)->orderBy('position')->get(),
            'totalWeight' => AssessmentType::totalWeight($school?->id),
            'balanced' => AssessmentType::weightsBalance($school?->id),
        ]);
    }

    public function storeAssessmentType(Request $request): RedirectResponse
    {
        $school = $this->school();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('assessment_types')->where('school_id', $school?->id)],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        $data['school_id'] = $school?->id;
        $data['code'] = strtolower($data['code']);
        $data['position'] ??= (AssessmentType::where('school_id', $school?->id)->max('position') ?? 0) + 1;

        AssessmentType::create($data);

        return back()->with('status', $this->weightNotice($school?->id, 'Assessment type added.'));
    }

    public function updateAssessmentType(Request $request, AssessmentType $assessmentType): RedirectResponse
    {
        $this->authorizeSchool($assessmentType->school_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('assessment_types')->where('school_id', $assessmentType->school_id)->ignore($assessmentType->id)],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = strtolower($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        $assessmentType->update($data);

        return back()->with('status', $this->weightNotice($assessmentType->school_id, 'Assessment type updated.'));
    }

    public function destroyAssessmentType(AssessmentType $assessmentType): RedirectResponse
    {
        $this->authorizeSchool($assessmentType->school_id);
        $schoolId = $assessmentType->school_id;
        $assessmentType->delete();

        return back()->with('status', $this->weightNotice($schoolId, 'Assessment type removed.'));
    }

    // ───────────────────────── helpers ─────────────────────────

    /** Warn — but never block — when component weights don't total 100. */
    private function weightNotice(?string $schoolId, string $message): string
    {
        if (! $schoolId || AssessmentType::weightsBalance($schoolId)) {
            return $message;
        }

        $total = AssessmentType::totalWeight($schoolId);

        return $message.' Note: your assessment weights currently total '.rtrim(rtrim(number_format($total, 2), '0'), '.').'%, not 100%.';
    }

    private function authorizeSchool(?string $schoolId): void
    {
        abort_unless($schoolId && $schoolId === $this->school()?->id, 403);
    }
}
