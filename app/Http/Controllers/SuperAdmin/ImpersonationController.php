<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controlled cross-guard access.
 *
 * Platform staff have no implicit rights inside a school. To provide support
 * they explicitly impersonate a school user, which issues a real `web` session
 * on that school's subdomain and records who is behind it.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, School $school, User $user): RedirectResponse
    {
        $admin = $request->user('superadmin');

        abort_unless($admin, 403);
        abort_unless($user->belongsToSchool($school->id), 404);

        Log::warning('Super admin impersonation started', [
            'super_admin_id' => $admin->id,
            'super_admin_email' => $admin->email,
            'school_id' => $school->id,
            'target_user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        Auth::guard('web')->login($user);

        $request->session()->put('impersonator_id', $admin->id);
        $request->session()->put('impersonated_school', $school->id);
        $request->session()->put('tenant_subdomain', $school->subdomain);

        return redirect($school->url('/dashboard'))
            ->with('status', 'You are now viewing '.$school->name.' as '.$user->name.'.');
    }

    /**
     * Return an admin to their own account.
     *
     * Separate from stop(): platform staff sign out of the school entirely,
     * whereas a school admin is logged back in as themselves. It carries no
     * role middleware because the person calling it is currently signed in as
     * the teacher or student being impersonated.
     */
    public function stopAdminImpersonation(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('admin_impersonator_id');

        abort_unless($adminId, 403);

        $admin = User::find($adminId);

        if (! $admin) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Log::info('Admin impersonation ended', [
            'admin_id' => $admin->id,
            'target_user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ]);

        Auth::guard('web')->login($admin);

        // The identity behind the session changed, so the id must not carry over.
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')
            ->with('status', 'You are back as yourself.');
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 403);

        Log::info('Super admin impersonation ended', [
            'super_admin_id' => $request->session()->get('impersonator_id'),
            'user_id' => $request->user()?->id,
        ]);

        Auth::guard('web')->logout();

        $request->session()->forget(['impersonator_id', 'impersonated_school']);

        return redirect()->to(
            (config('tenancy.domain')
                ? config('tenancy.scheme', 'https').'://'.config('tenancy.central_subdomain').'.'.config('tenancy.domain')
                : rtrim(config('app.url'), '/').'/super-admin')
        );
    }
}
