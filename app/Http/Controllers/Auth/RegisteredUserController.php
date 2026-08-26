<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenancy\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     *  - On a SCHOOL SUBDOMAIN: the new account becomes a student of that
     *    school immediately.
     *  - On the MAIN DOMAIN: the account starts with no role and is taken to
     *    onboarding (create a school as its admin, or join via a class code).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $school = Tenant::school();

        if ($school) {
            Student::firstOrCreate([
                'user_id' => $user->id,
                'school_id' => $school->id,
            ]);
            session(['active_school_id' => $school->id]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect($school
            ? route('student.dashboard', absolute: false)
            : route('dashboard', absolute: false));
    }
}
