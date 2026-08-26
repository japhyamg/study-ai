<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Tenancy\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * On a school subdomain the school name is shown so users always know
     * which school they are signing in to.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * Everyone signs in through this one route. What happens next depends on
     * the user's type and where they signed in from:
     *
     *  - School subdomain: the account must belong to that school.
     *  - Main domain:      platform admins land on the super-admin dashboard;
     *                      school users are redirected to their school's
     *                      subdomain (when one is configured).
     *  - 2FA-enabled accounts go through a one-time-code challenge first.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();
        $school = Tenant::school();

        // Tenant scope — the account must belong to the school on this subdomain.
        if ($school && ! $user->isSuperAdmin() && ! $user->belongsToSchool($school->id)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => "This account is not a member of {$school->name}.",
            ]);
        }

        // Two-factor challenge comes before the session is fully established.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->regenerate();
            $request->session()->put('2fa', [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
            ]);
            Auth::guard('web')->logout();

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        if ($school) {
            session(['active_school_id' => $school->id]);
        }

        return $this->redirectAfterLogin($request, $user);
    }

    protected function redirectAfterLogin(Request $request, $user): RedirectResponse
    {
        // School users signing in on the main domain go to their school's workspace.
        if (Tenant::isCentral() && ! $user->isSuperAdmin()) {
            $school = $user->currentSchool();
            if ($school && ($url = $school->appUrl())) {
                return redirect()->away($url.$this->intendedPath($request));
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /** Keep only the path of the intended URL so it is reused on the school domain. */
    protected function intendedPath(Request $request): string
    {
        $intended = redirect()->intended(route('dashboard'))->getTargetUrl();
        $path = parse_url($intended, PHP_URL_PATH) ?: '/dashboard';
        $query = parse_url($intended, PHP_URL_QUERY);

        return $query ? $path.'?'.$query : $path;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
