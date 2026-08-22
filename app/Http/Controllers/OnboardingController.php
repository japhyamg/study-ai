<?php

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\ClassModel;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Support\Members\MemberTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $user = auth()->user();

        if ($user->highestRole() !== null) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.index');
    }

    /**
     * Create a new school and become its administrator.
     */
    public function createSchool(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_if($user->highestRole() !== null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'alpha_dash'],
        ]);

        $slug = $this->uniqueSlug($data['slug'] ?: $data['name']);

        $school = School::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        SchoolAdmin::firstOrCreate([
            'user_id' => $user->id,
            'school_id' => $school->id,
        ]);

        session(['active_school_id' => $school->id]);

        $url = $school->appUrl();

        return redirect()->to($url ? $url.route('admin.dashboard', [], false) : route('admin.dashboard'))
            ->with('status', "School \"{$school->name}\" created. You're the admin.");
    }

    /**
     * Join an existing class by its invite code.
     */
    public function join(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_if($user->highestRole() !== null, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $class = ClassModel::where('invite_code', $data['code'])->first();
        if (! $class) {
            return back()->withErrors(['code' => 'No class found for that code.']);
        }

        // Attach to the class's school as a student.
        Student::firstOrCreate([
            'user_id' => $user->id,
            'school_id' => $class->school_id,
        ]);

        ClassEnrollment::firstOrCreate([
            'class_id' => $class->id,
            'user_id' => $user->id,
        ]);

        session(['active_school_id' => $class->school_id]);

        $school = $class->school;
        $url = $school?->appUrl();

        return redirect()->to($url ? $url.route('student.dashboard', [], false) : route('student.dashboard'))
            ->with('status', "Joined \"{$class->name}\".");
    }

    /** Build a school subdomain slug that is unique and not reserved. */
    protected function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'school';
        $base = substr($base, 0, 40);
        $slug = $base;

        while (School::where('slug', $slug)->exists()
            || in_array($slug, config('tenancy.reserved_slugs'), true)) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
