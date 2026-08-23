<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Apex-domain landing page and "which school am I?" helper. */
class SchoolLookupController extends Controller
{
    public function landing(): View
    {
        return view('public.landing');
    }

    public function show(): View
    {
        return view('public.find-school');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $request->validate([
            'subdomain' => ['required', 'string', 'max:60'],
        ]);

        $needle = trim(strtolower($request->string('subdomain')->toString()));

        $school = School::query()
            ->where('subdomain', $needle)
            ->orWhere('slug', $needle)
            ->first();

        if (! $school) {
            return back()
                ->withInput()
                ->withErrors(['subdomain' => 'We could not find a school with that address.']);
        }

        return redirect()->away($school->url('/login'));
    }
}
