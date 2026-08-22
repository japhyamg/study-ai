<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * The user's account page — profile information, password and 2FA.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        $setupSecret = null;
        $setupUri = null;

        // Step "scan the QR" state (secret generated, not yet confirmed).
        if ($user->two_factor_secret && ! $user->hasTwoFactorEnabled()) {
            $setupSecret = $user->two_factor_secret;
            $setupUri = Totp::uri($user->email, $setupSecret, config('app.name', 'StudyAI'));
        }

        return view('profile.edit', [
            'user' => $user,
            'setupSecret' => $setupSecret,
            'setupUri' => $setupUri,
            'recoveryCodes' => $request->get('two_factor') === 'codes' ? $user->twoFactorRecoveryCodes() : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return back()->with('status', 'Profile updated.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
