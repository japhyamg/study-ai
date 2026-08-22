<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\School;
use App\Models\SchoolMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if ($user->memberships()->exists()) {
            return redirect()->route('dashboard');
        }
        return view('onboarding.index');
    }

    /**
     * Create a new school and become its admin.
     */
    public function createSchool(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_if($user->memberships()->exists(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $school = School::create([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name']) . '-' . substr((string) \Illuminate\Support\Str::uuid(), 0, 6),
        ]);

        SchoolMember::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'role' => SchoolMember::ROLE_ADMIN,
        ]);

        session(['active_school_id' => $school->id]);

        return redirect()->route('admin.dashboard')->with('status', "School \"{$school->name}\" created. You're the admin.");
    }

    /**
     * Join an existing class by its invite code.
     */
    public function join(Request $request): RedirectResponse
    {
        $user = auth()->user();
        abort_if($user->memberships()->exists(), 403);

        $data = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $class = ClassModel::where('invite_code', $data['code'])->first();
        if (! $class) {
            return back()->withErrors(['code' => 'No class found for that code.']);
        }

        // Attach to the class's school as a student.
        SchoolMember::firstOrCreate([
            'user_id' => $user->id,
            'school_id' => $class->school_id,
        ], [
            'role' => SchoolMember::ROLE_STUDENT,
        ]);

        \App\Models\ClassEnrollment::firstOrCreate([
            'class_id' => $class->id,
            'user_id' => $user->id,
        ]);

        session(['active_school_id' => $class->school_id]);

        return redirect()->route('student.dashboard')->with('status', "Joined \"{$class->name}\".");
    }
}
