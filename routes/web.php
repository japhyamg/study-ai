<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TwoFactorController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Route map (SaaS tenancy)
|--------------------------------------------------------------------------
|
|  MAIN DOMAIN (central)                     SCHOOL SUBDOMAIN ({slug}.domain)
|  ├─ /login, /register (shared)             ├─ /login, /register (scoped to the school)
|  ├─ /super-admin/*  (platform only)        ├─ /admin/*, /teacher/*, /student/*
|  ├─ /onboarding (create/join a school)     ├─ /profile, /two-factor/*
|  └─ /profile, /two-factor/*                └─ /dashboard → role dispatch
|
|  School users signing in on the main domain are redirected to their
|  school's subdomain automatically (when APP_CENTRAL_DOMAINS is set).
|  In local development everything also works path-based on localhost.
|
*/

Route::redirect('/', '/dashboard');

// Role-based home dispatch
Route::get('/dashboard', function () {
    $user = Auth::user();
    if (! $user) {
        return redirect()->route('login');
    }

    return match ($user->highestRole()) {
        User::ROLE_SUPER_ADMIN => redirect()->route('super-admin.dashboard'),
        User::ROLE_ADMIN => redirect()->route('admin.dashboard'),
        User::ROLE_TEACHER => redirect()->route('teacher.dashboard'),
        User::ROLE_STUDENT => redirect()->route('student.dashboard'),
        default => redirect()->route('onboarding'),
    };
})->middleware(['auth'])->name('dashboard');

// Two-factor challenge (second login step for 2FA accounts)
Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'show'])
    ->name('two-factor.challenge');
Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('two-factor.verify');
Route::post('two-factor-challenge/cancel', [TwoFactorChallengeController::class, 'logout'])
    ->name('two-factor.cancel');

// Onboarding (central — no school yet)
Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');
Route::post('onboarding/school', [OnboardingController::class, 'createSchool'])->name('onboarding.school');
Route::post('onboarding/join', [OnboardingController::class, 'join'])->name('onboarding.join');

// ── Teacher ──
Route::middleware(['auth', 'role:teacher,admin,super_admin'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('dashboard', [TeacherController::class, 'dashboard'])->name('dashboard');
    Route::get('classes', [TeacherController::class, 'teacherClasses'])->name('classes.index');
    Route::get('classes/{class}', [TeacherController::class, 'teacherClassShow'])->name('classes.show');
    Route::get('exams', [TeacherController::class, 'exams'])->name('exams.index');
    Route::get('exams/new', [TeacherController::class, 'createExam'])->name('exams.create');
    Route::post('exams', [TeacherController::class, 'storeExam'])->name('exams.store');
    Route::get('exams/{exam}', [TeacherController::class, 'showExam'])->name('exams.show');
    Route::get('exams/{exam}/edit', [TeacherController::class, 'editExam'])->name('exams.edit');
    Route::put('exams/{exam}', [TeacherController::class, 'updateExam'])->name('exams.update');
    Route::put('exams/{exam}/publish', [TeacherController::class, 'publishExam'])->name('exams.publish');
    Route::put('exams/{exam}/unpublish', [TeacherController::class, 'unpublishExam'])->name('exams.unpublish');
    Route::delete('exams/{exam}', [TeacherController::class, 'destroyExam'])->name('exams.destroy');
    Route::post('exams/{exam}/questions', [TeacherController::class, 'addQuestion'])->name('exams.questions.store');
    Route::delete('exams/{exam}/questions/{question}', [TeacherController::class, 'removeQuestion'])->name('exams.questions.destroy');
    Route::get('materials', [TeacherController::class, 'materialsIndex'])->name('materials.index');
    Route::get('materials/create', [TeacherController::class, 'materialsCreate'])->name('materials.create');
    Route::post('materials', [TeacherController::class, 'materialsStore'])->name('materials.store');
    Route::get('materials/review', [TeacherController::class, 'reviewMaterials'])->name('materials.review');
    Route::get('materials/{material}', [TeacherController::class, 'materialsShow'])->name('materials.show');
    Route::get('materials/{material}/edit', [TeacherController::class, 'materialsEdit'])->name('materials.edit');
    Route::put('materials/{material}', [TeacherController::class, 'materialsUpdate'])->name('materials.update');
    Route::delete('materials/{material}', [TeacherController::class, 'destroyMaterial'])->name('materials.destroy');
    Route::post('materials/{material}/approve-all', [TeacherController::class, 'materialsApproveAll'])->name('materials.approve-all');
    Route::put('materials/{material}/approve', [TeacherController::class, 'approveMaterial'])->name('materials.approve');
    Route::put('materials/{material}/reject', [TeacherController::class, 'rejectMaterial'])->name('materials.reject');

    // Per-flashcard edit/delete (used by the tabbed material detail)
    Route::put('flashcards/{flashcard}', [TeacherController::class, 'updateFlashcard'])->name('flashcards.update');
    Route::delete('flashcards/{flashcard}', [TeacherController::class, 'destroyFlashcard'])->name('flashcards.destroy');
    // Per-question edit/delete (used by the tabbed material detail)
    Route::put('questions/{question}', [TeacherController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('questions/{question}', [TeacherController::class, 'destroyQuestion'])->name('questions.destroy');

    Route::get('exams/{exam}/analytics', [TeacherController::class, 'examAnalytics'])->name('exams.analytics');

    Route::get('question-bank', [TeacherController::class, 'questionBankIndex'])->name('question-bank.index');
    Route::post('question-bank', [TeacherController::class, 'questionBankStore'])->name('question-bank.store');
    Route::delete('question-bank/{qb}', [TeacherController::class, 'questionBankDestroy'])->name('question-bank.destroy');
});

// ── Student ──
Route::middleware(['auth', 'role:student,admin,super_admin'])->prefix('student')->name('student.')->group(function () {
    Route::get('dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
    Route::get('classes', [StudentController::class, 'classes'])->name('classes');
    Route::get('classes/{enrollment}', [StudentController::class, 'classShow'])->name('classes.show');
    Route::get('materials', [StudentController::class, 'materials'])->name('materials');
    Route::get('exams', [StudentController::class, 'exams'])->name('exams');
    Route::post('exams/{exam}/start', [StudentController::class, 'startExam'])->name('exams.start');
    Route::get('exams/{exam}/take/{attempt}', [StudentController::class, 'takeExam'])->name('exams.take');
    Route::post('exams/{exam}/attempt/{attempt}', [StudentController::class, 'submitExam'])->name('exams.submit');
    Route::get('exams/{exam}/result/{attempt}', [StudentController::class, 'examResult'])->name('exams.result');
    Route::get('flashcards', [StudentController::class, 'flashcards'])->name('flashcards');
    Route::post('flashcards/{flashcard}/review', [StudentController::class, 'reviewFlashcard'])->name('flashcards.review');
    Route::get('topics', [TopicController::class, 'index'])->name('topics.index');
    Route::post('topics/generate', [TopicController::class, 'generate'])->name('topics.generate');
    Route::delete('topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

    // Study mode (guided flashcard review session)
    Route::get('study', [StudentController::class, 'studyIndex'])->name('study.index');
    Route::get('study/session', [StudentController::class, 'studySession'])->name('study.session');
    Route::get('study/session/{material}', [StudentController::class, 'studySession'])->name('study.session.material');
    Route::get('study/{material}', [StudentController::class, 'studyHub'])->name('study.hub');
    Route::post('study/{flashcard}/answer', [StudentController::class, 'studyAnswer'])->name('study.answer');
});

Route::middleware(['auth'])->group(function () {
    // ── Super Admin (platform — main domain only) ──
    Route::middleware(['central'])->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/', [SuperAdminController::class, 'dashboard'])->name('dashboard');
        Route::get('analytics', [SuperAdminController::class, 'analytics'])->name('analytics');
        Route::get('usage-teachers', [SuperAdminController::class, 'usageTeachers'])->name('usage-teachers');
        Route::get('schools', [SuperAdminController::class, 'schools'])->name('schools');
        Route::get('schools/{school}', [SuperAdminController::class, 'schoolDetail'])->name('schools.show');
        Route::post('schools', [SuperAdminController::class, 'storeSchool'])->name('schools.store');
        Route::put('schools/{school}', [SuperAdminController::class, 'updateSchool'])->name('schools.update');
        Route::delete('schools/{school}', [SuperAdminController::class, 'destroySchool'])->name('schools.destroy');
        Route::put('schools/{school}/members/{type}/{id}', [SuperAdminController::class, 'updateMemberRoleInSchool'])->name('schools.members.role');

        Route::get('ai-providers', [SuperAdminController::class, 'aiProviders'])->name('ai-providers');
        Route::post('ai-providers', [SuperAdminController::class, 'storeAiProvider'])->name('ai-providers.store');
        Route::put('ai-providers/{ai_provider}', [SuperAdminController::class, 'updateAiProvider'])->name('ai-providers.update');
        Route::delete('ai-providers/{ai_provider}', [SuperAdminController::class, 'destroyAiProvider'])->name('ai-providers.destroy');

        Route::get('token-limits', [SuperAdminController::class, 'tokenLimits'])->name('token-limits');
        Route::put('token-limits/default', [SuperAdminController::class, 'setDefaultTokenLimit'])->name('token-limits.default');
        Route::put('token-limits/{userId}', [SuperAdminController::class, 'setTeacherTokenLimit'])->name('token-limits.user');
        Route::get('token-usage', [SuperAdminController::class, 'tokenUsage'])->name('token-usage');
    });

    // ── Admin (school-scoped) ──
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('classes', [AdminController::class, 'classes'])->name('classes.index');
        Route::get('classes/new', [AdminController::class, 'createClass'])->name('classes.create');
        Route::post('classes', [AdminController::class, 'storeClass'])->name('classes.store');
        Route::get('classes/{class}', [AdminController::class, 'showClass'])->name('classes.show');
        Route::get('classes/{class}/edit', [AdminController::class, 'editClass'])->name('classes.edit');
        Route::put('classes/{class}', [AdminController::class, 'updateClass'])->name('classes.update');
        Route::delete('classes/{class}', [AdminController::class, 'destroyClass'])->name('classes.destroy');
        Route::put('classes/{class}/assign-teacher', [AdminController::class, 'assignTeacher'])->name('classes.assign-teacher');
        Route::post('classes/{class}/enroll', [AdminController::class, 'enrollStudent'])->name('classes.enroll');
        Route::delete('classes/{class}/enroll/{userId}', [AdminController::class, 'unenrollStudent'])->name('classes.unenroll');
        Route::get('classes/{class}/invite-codes', [AdminController::class, 'inviteCodes'])->name('classes.invite-codes');
        Route::post('classes/{class}/invite-codes', [AdminController::class, 'storeInviteCode'])->name('classes.invite-codes.store');

        Route::get('members', [AdminController::class, 'members'])->name('members');
        Route::post('members/invite', [AdminController::class, 'inviteMember'])->name('members.invite');
        Route::post('members/bulk-invite', [AdminController::class, 'bulkInviteMembers'])->name('members.bulk-invite');
        Route::put('members/{type}/{id}/role', [AdminController::class, 'updateMemberRole'])->name('members.role');
        Route::delete('members/{type}/{id}', [AdminController::class, 'removeMember'])->name('members.remove');

        Route::get('settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('settings', [AdminController::class, 'updateSettings'])->name('settings.update');

        Route::get('subjects', [AdminController::class, 'subjects'])->name('subjects.index');
        Route::post('subjects', [AdminController::class, 'storeSubject'])->name('subjects.store');
        Route::put('subjects/{subject}', [AdminController::class, 'updateSubject'])->name('subjects.update');
        Route::delete('subjects/{subject}', [AdminController::class, 'destroySubject'])->name('subjects.destroy');

        Route::get('terms', [AdminController::class, 'terms'])->name('terms.index');
        Route::post('terms', [AdminController::class, 'storeTerm'])->name('terms.store');
        Route::put('terms/{term}', [AdminController::class, 'updateTerm'])->name('terms.update');
        Route::delete('terms/{term}', [AdminController::class, 'destroyTerm'])->name('terms.destroy');
    })->middleware('role:admin,super_admin');

    // ── Profile (works on every domain) ──
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Two-factor authentication ──
    Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])->name('two-factor.regenerate');

    // ── Materials (shared by teacher/student/admin) ──
    Route::middleware(['role:teacher,student,admin,super_admin'])->prefix('materials')->name('materials.')->group(function () {
        Route::get('{material}', [MaterialController::class, 'show'])->name('show');
    });
    Route::middleware(['role:teacher,admin,super_admin'])->prefix('materials')->name('materials.')->group(function () {
        Route::post('{material}/generate', [MaterialController::class, 'generate'])->name('generate');
        Route::get('jobs/{job}', [MaterialController::class, 'jobStatus'])->name('job.status');
    });
});
