<?php

namespace App\Http\Controllers;

use App\Models\ClassArm;
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

        $code = strtoupper(trim($data['code']));

        // The code may be the arm's own code, or a generated InviteCode row.
        $class = ClassArm::where('invite_code', $code)->first();

        if (! $class) {
            $invite = \App\Models\InviteCode::where('code', $code)->first();

            if ($invite && $invite->class_arm_id) {
                if ($invite->expires_at && $invite->expires_at->isPast()) {
                    return back()->withErrors(['code' => 'That invite code has expired.']);
                }

                if ($invite->max_uses && $invite->used_count >= $invite->max_uses) {
                    return back()->withErrors(['code' => 'That invite code has already been used the maximum number of times.']);
                }

                $class = $invite->classArm;
                $invite->increment('used_count');
            }
        }

        if (! $class) {
            return back()->withErrors(['code' => 'No class found for that code.']);
        }

        if ($class->isFull()) {
            return back()->withErrors(['code' => 'That class is already at capacity. Ask your school for help.']);
        }

        // Attach to the class's school as a student.
        SchoolMember::firstOrCreate([
            'user_id' => $user->id,
            'school_id' => $class->school_id,
        ], [
            'role' => SchoolMember::ROLE_STUDENT,
        ]);

        \App\Models\ClassEnrollment::firstOrCreate(
            ['class_arm_id' => $class->id, 'user_id' => $user->id],
            ['role' => 'student', 'enrolled_at' => now()]
        );

        session(['active_school_id' => $class->school_id]);

        return redirect()->route('student.dashboard')->with('status', 'Joined \''.$class->fullName().'\'.');
    }
}
