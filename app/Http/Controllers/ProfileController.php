<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\SchoolMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Every school user — administrator, teacher or student — manages their own
 * account here: identity, role-specific details, password, 2FA and preferences.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing(['adminProfile', 'teacherProfile', 'studentProfile', 'school']);

        return view('profile.edit', [
            'user' => $user,
            'profile' => $user->profile(),
            'role' => $user->roleInSchool(),
            'tab' => $request->query('tab', 'profile'),
        ]);
    }

    /** Name, email, phone, avatar. */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($user->image && ! str_starts_with($user->image, 'http')) {
                Storage::disk('public')->delete($user->image);
            }

            $data['image'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($data['avatar']);

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Profile updated.');
    }

    /** Role-specific fields, written to the matching profile table. */
    public function updateRoleDetails(Request $request): RedirectResponse
    {
        $user = $request->user();
        $school = $user->currentSchool();

        abort_unless($school, 403);

        match ($user->roleInSchool()) {
            SchoolMember::ROLE_ADMIN => $this->updateAdminProfile($request, $user, $school->id),
            SchoolMember::ROLE_TEACHER => $this->updateTeacherProfile($request, $user, $school->id),
            SchoolMember::ROLE_STUDENT => $this->updateStudentProfile($request, $user, $school->id),
            default => abort(403),
        };

        return back()->with('status', 'Details updated.');
    }

    /** Interface preferences (locale, timezone). */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:12'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Preferences saved.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // A school's last administrator may not delete themselves.
        if ($user->isAdmin()) {
            $remaining = SchoolMember::where('school_id', $user->school_id)
                ->where('role', SchoolMember::ROLE_ADMIN)
                ->where('user_id', '!=', $user->id)
                ->count();

            if ($remaining === 0) {
                return back()->withErrors([
                    'password' => 'You are the only administrator for this school. Assign another administrator before deleting your account.',
                ], 'userDeletion');
            }
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account has been deleted.');
    }

    // ── Per-role writers ──

    private function updateAdminProfile(Request $request, $user, string $schoolId): void
    {
        $data = $request->validate([
            'staff_number' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'office_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $user->adminProfile()->updateOrCreate(
            ['user_id' => $user->id, 'school_id' => $schoolId],
            $data
        );
    }

    private function updateTeacherProfile(Request $request, $user, string $schoolId): void
    {
        $data = $request->validate([
            'staff_number' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:120'],
            'qualification' => ['nullable', 'string', 'max:180'],
            'office_hours' => ['nullable', 'string', 'max:180'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'specialisations' => ['nullable', 'string', 'max:500'],
        ]);

        if (isset($data['specialisations'])) {
            $data['specialisations'] = collect(explode(',', $data['specialisations']))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->values()
                ->all();
        }

        $user->teacherProfile()->updateOrCreate(
            ['user_id' => $user->id, 'school_id' => $schoolId],
            $data
        );
    }

    private function updateStudentProfile(Request $request, $user, string $schoolId): void
    {
        // Students may maintain contact details; academic identity fields
        // (admission number, grade) stay administrator-controlled.
        $data = $request->validate([
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:32'],
            'guardian_name' => ['nullable', 'string', 'max:160'],
            'guardian_phone' => ['nullable', 'string', 'max:40'],
            'guardian_email' => ['nullable', 'email', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id, 'school_id' => $schoolId],
            $data
        );
    }
}
