<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SuperAdminProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('super-admin.profile', [
            'admin' => $request->user('superadmin'),
            'tab' => $request->query('tab', 'profile'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $request->user('superadmin');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:super_admins,email,'.$admin->id],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $admin->fill($data);

        if ($admin->isDirty('email')) {
            $admin->email_verified_at = null;
        }

        $admin->save();

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password:superadmin'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user('superadmin')->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Password updated.');
    }
}
