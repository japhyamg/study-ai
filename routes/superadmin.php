<?php

use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\SuperAdminAuthController;
use App\Http\Controllers\SuperAdmin\SuperAdminProfileController;
use App\Http\Controllers\SuperAdmin\SuperAdminTwoFactorController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform (super-admin) routes — central domain
|--------------------------------------------------------------------------
|
| Served from admin.{APP_DOMAIN}. These use the `superadmin` guard, which is
| backed by its own table, so platform staff and school users can never be
| confused for one another.
|
| Route names are prefixed with `super-admin.` by bootstrap/app.php.
|
*/

// ── Guest ──
Route::middleware('guest:superadmin')->group(function () {
    Route::get('login', [SuperAdminAuthController::class, 'create'])->name('login');
    Route::post('login', [SuperAdminAuthController::class, 'store'])->name('login.store');

    Route::get('two-factor', [SuperAdminAuthController::class, 'twoFactorChallenge'])
        ->name('two-factor.challenge');
    Route::post('two-factor', [SuperAdminAuthController::class, 'verifyTwoFactor'])
        ->name('two-factor.verify');
});

Route::post('logout', [SuperAdminAuthController::class, 'destroy'])
    ->middleware('auth:superadmin')
    ->name('logout');

// ── Authenticated platform area ──
Route::middleware(['auth:superadmin', '2fa'])->group(function () {

    Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('analytics', [SuperAdminController::class, 'analytics'])->name('analytics');
    Route::get('usage-teachers', [SuperAdminController::class, 'usageTeachers'])->name('usage-teachers');

    // Schools (tenants)
    Route::get('schools', [SuperAdminController::class, 'schools'])->name('schools');
    Route::post('schools', [SuperAdminController::class, 'storeSchool'])->name('schools.store');
    Route::get('schools/{school}', [SuperAdminController::class, 'schoolDetail'])->name('schools.show');
    Route::put('schools/{school}', [SuperAdminController::class, 'updateSchool'])->name('schools.update');
    Route::delete('schools/{school}', [SuperAdminController::class, 'destroySchool'])->name('schools.destroy');
    Route::put('schools/{school}/status', [SuperAdminController::class, 'updateSchoolStatus'])->name('schools.status');
    Route::put('schools/{school}/members/{member}', [SuperAdminController::class, 'updateMemberRoleInSchool'])
        ->name('schools.members.role');

    // Impersonation — the only way platform staff enter a tenant
    Route::post('schools/{school}/impersonate/{user}', [ImpersonationController::class, 'start'])
        ->name('impersonate.start');

    // AI providers
    Route::get('ai-providers', [SuperAdminController::class, 'aiProviders'])->name('ai-providers');
    Route::post('ai-providers', [SuperAdminController::class, 'storeAiProvider'])->name('ai-providers.store');
    Route::put('ai-providers/{ai_provider}', [SuperAdminController::class, 'updateAiProvider'])->name('ai-providers.update');
    Route::delete('ai-providers/{ai_provider}', [SuperAdminController::class, 'destroyAiProvider'])->name('ai-providers.destroy');

    // Token governance
    Route::get('token-limits', [SuperAdminController::class, 'tokenLimits'])->name('token-limits');
    Route::put('token-limits/default', [SuperAdminController::class, 'setDefaultTokenLimit'])->name('token-limits.default');
    Route::put('token-limits/{userId}', [SuperAdminController::class, 'setTeacherTokenLimit'])->name('token-limits.user');
    Route::get('token-usage', [SuperAdminController::class, 'tokenUsage'])->name('token-usage');

    // ── Own profile & security ──
    Route::get('profile', [SuperAdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [SuperAdminProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [SuperAdminProfileController::class, 'updatePassword'])->name('profile.password');

    Route::post('profile/two-factor', [SuperAdminTwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('profile/two-factor/confirm', [SuperAdminTwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('profile/two-factor/recovery-codes', [SuperAdminTwoFactorController::class, 'regenerate'])->name('two-factor.recovery-codes');
    Route::delete('profile/two-factor', [SuperAdminTwoFactorController::class, 'disable'])->name('two-factor.disable');
});
