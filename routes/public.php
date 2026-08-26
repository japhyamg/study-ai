<?php

use App\Http\Controllers\Public\SchoolLookupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (apex domain)
|--------------------------------------------------------------------------
|
| Marketing surface and the "find my school" helper that redirects a person
| to the right subdomain when they land on the apex domain by mistake.
|
*/

Route::get('/', [SchoolLookupController::class, 'landing'])->name('landing');

Route::get('find-school', [SchoolLookupController::class, 'show'])->name('school.find');
Route::post('find-school', [SchoolLookupController::class, 'lookup'])->name('school.lookup');
