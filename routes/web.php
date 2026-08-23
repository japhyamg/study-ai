<?php

use App\Http\Controllers\Admin\AcademicController;
use App\Http\Controllers\Admin\ClassArmController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Learning\MaterialGenerationController;
use App\Http\Controllers\Learning\MaterialWorkflowController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TwoFactorController;
use App\Models\SchoolMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant routes — {school}.{APP_DOMAIN}
|--------------------------------------------------------------------------
|
| Every route here runs inside a resolved school. Administrators, teachers and
| students all authenticate through the same login route; what they can reach
| afterwards is decided by their role within this school.
|
*/

require __DIR__.'/auth.php';

Route::redirect('/', '/dashboard')->name('home');

// Role-based dispatch
Route::get('/dashboard', function () {
    $user = Auth::user();

    if (! $user) {
        return redirect()->route('login');
    }

    return match ($user->roleInSchool()) {
        SchoolMember::ROLE_ADMIN => redirect()->route('admin.dashboard'),
        SchoolMember::ROLE_TEACHER => redirect()->route('teacher.dashboard'),
        SchoolMember::ROLE_STUDENT => redirect()->route('student.dashboard'),
        default => redirect()->route('onboarding'),
    };
})->middleware(['auth', 'school.user', '2fa'])->name('dashboard');

// Onboarding (authenticated but not yet attached to this school)
Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');
    Route::post('onboarding/school', [OnboardingController::class, 'createSchool'])->name('onboarding.school');
    Route::post('onboarding/join', [OnboardingController::class, 'join'])->name('onboarding.join');
});

/*
|--------------------------------------------------------------------------
| Profile & account security — every signed-in school user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.user', '2fa'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/details', [ProfileController::class, 'updateRoleDetails'])->name('profile.details');
    Route::put('profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Two-factor authentication
    Route::post('profile/two-factor', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('profile/two-factor/recovery-codes', [TwoFactorController::class, 'regenerate'])->name('two-factor.recovery-codes');
    Route::delete('profile/two-factor', [TwoFactorController::class, 'disable'])->name('two-factor.disable');

    // Leave an impersonation session started by platform staff
    Route::post('stop-impersonating', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    // Exiting must work while signed in as the impersonated teacher or student,
    // so it cannot sit behind AdminController's role middleware.
    Route::post('stop-admin-impersonating', [ImpersonationController::class, 'stopAdminImpersonation'])
        ->name('admin.impersonate.stop');
});

/*
|--------------------------------------------------------------------------
| Administrator
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.user', '2fa', 'role:admin'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('analytics', [AdminController::class, 'analytics'])->name('analytics');

        // ── Academic calendar & structure ──
        Route::get('academic', [AcademicController::class, 'index'])->name('academic.index');
        Route::post('academic/bootstrap', [AcademicController::class, 'bootstrap'])->name('academic.bootstrap');

        Route::post('sessions', [AcademicController::class, 'storeSession'])->name('sessions.store');
        Route::put('sessions/{session}', [AcademicController::class, 'updateSession'])->name('sessions.update');
        Route::put('sessions/{session}/activate', [AcademicController::class, 'activateSession'])->name('sessions.activate');
        Route::delete('sessions/{session}', [AcademicController::class, 'destroySession'])->name('sessions.destroy');

        Route::get('terms', [AcademicController::class, 'terms'])->name('terms.index');
        Route::post('terms', [AcademicController::class, 'storeTerm'])->name('terms.store');
        Route::put('terms/{term}', [AcademicController::class, 'updateTerm'])->name('terms.update');
        Route::put('terms/{term}/activate', [AcademicController::class, 'activateTerm'])->name('terms.activate');
        Route::delete('terms/{term}', [AcademicController::class, 'destroyTerm'])->name('terms.destroy');

        Route::get('levels', [AcademicController::class, 'levels'])->name('levels.index');
        Route::post('levels', [AcademicController::class, 'storeLevel'])->name('levels.store');
        Route::put('levels/{level}', [AcademicController::class, 'updateLevel'])->name('levels.update');
        Route::delete('levels/{level}', [AcademicController::class, 'destroyLevel'])->name('levels.destroy');

        Route::get('assessment-types', [AcademicController::class, 'assessmentTypes'])->name('assessment-types.index');
        Route::post('assessment-types', [AcademicController::class, 'storeAssessmentType'])->name('assessment-types.store');
        Route::put('assessment-types/{assessmentType}', [AcademicController::class, 'updateAssessmentType'])->name('assessment-types.update');
        Route::delete('assessment-types/{assessmentType}', [AcademicController::class, 'destroyAssessmentType'])->name('assessment-types.destroy');

        // ── Classes (arms) ──
        Route::get('classes', [ClassArmController::class, 'index'])->name('classes.index');
        Route::get('classes/new', [ClassArmController::class, 'create'])->name('classes.create');
        Route::post('classes', [ClassArmController::class, 'store'])->name('classes.store');
        Route::get('classes/{class}', [ClassArmController::class, 'show'])->name('classes.show');
        Route::get('classes/{class}/edit', [ClassArmController::class, 'edit'])->name('classes.edit');
        Route::put('classes/{class}', [ClassArmController::class, 'update'])->name('classes.update');
        Route::delete('classes/{class}', [ClassArmController::class, 'destroy'])->name('classes.destroy');

        Route::post('classes/{class}/enroll', [ClassArmController::class, 'enroll'])->name('classes.enroll');
        Route::delete('classes/{class}/enroll/{userId}', [ClassArmController::class, 'unenroll'])->name('classes.unenroll');
        Route::post('classes/{class}/subjects', [ClassArmController::class, 'assignSubject'])->name('classes.subjects.assign');
        Route::delete('classes/{class}/subjects', [ClassArmController::class, 'unassignSubject'])->name('classes.subjects.unassign');
        Route::post('classes/{class}/promote', [ClassArmController::class, 'promote'])->name('classes.promote');
        Route::get('classes/{class}/invite-codes', [ClassArmController::class, 'inviteCodes'])->name('classes.invite-codes');
        Route::post('classes/{class}/invite-codes', [ClassArmController::class, 'storeInviteCode'])->name('classes.invite-codes.store');

        // People — one screen per user type, backed by separate profile tables
        Route::get('teachers', [AdminController::class, 'teachers'])->name('teachers');
        Route::get('students', [AdminController::class, 'students'])->name('students');
        Route::get('administrators', [AdminController::class, 'administrators'])->name('administrators');
        // Declared before people/{user}: a wildcard segment would otherwise
        // match "import" and try to resolve it as a user id.
        Route::get('people/import/{role}', [AdminController::class, 'importForm'])->name('people.import');
        Route::get('people/import/{role}/template', [AdminController::class, 'importTemplate'])->name('people.import.template');
        Route::post('people/import', [AdminController::class, 'importPeople'])->name('people.import.store');

        Route::get('people/{user}', [AdminController::class, 'showUser'])->name('people.show');
        Route::put('people/{user}', [AdminController::class, 'updateUser'])->name('people.update');
        // People are added directly, each role through its own form.
        Route::get('teachers/new', [AdminController::class, 'createTeacher'])->name('teachers.create');
        Route::get('students/new', [AdminController::class, 'createStudent'])->name('students.create');
        Route::get('administrators/new', [AdminController::class, 'createAdministrator'])->name('administrators.create');
        Route::post('people', [AdminController::class, 'storePerson'])->name('people.store');
        Route::put('people/{user}/password', [AdminController::class, 'resetPassword'])->name('people.password');
        Route::post('people/{user}/impersonate', [AdminController::class, 'impersonate'])->name('people.impersonate');
        Route::delete('people/{user}', [AdminController::class, 'destroyUser'])->name('people.destroy');
        Route::delete('members/{member}', [AdminController::class, 'removeMember'])->name('members.remove');
        Route::put('members/{member}', [AdminController::class, 'updateMemberRole'])->name('members.role');

        Route::get('settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('settings', [AdminController::class, 'updateSettings'])->name('settings.update');

        Route::get('subjects', [AdminController::class, 'subjects'])->name('subjects.index');
        Route::post('subjects', [AdminController::class, 'storeSubject'])->name('subjects.store');
        Route::put('subjects/{subject}', [AdminController::class, 'updateSubject'])->name('subjects.update');
        Route::delete('subjects/{subject}', [AdminController::class, 'destroySubject'])->name('subjects.destroy');

    });

/*
|--------------------------------------------------------------------------
| Teacher
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.user', '2fa', 'role:teacher,admin'])
    ->prefix('teacher')->name('teacher.')->group(function () {
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
        Route::post('exams/{exam}/questions/from-bank', [TeacherController::class, 'importBankQuestions'])->name('exams.questions.from-bank');
        Route::put('exams/{exam}/questions/{question}', [TeacherController::class, 'updateExamQuestion'])->name('exams.questions.update');
        Route::delete('exams/{exam}/questions/{question}', [TeacherController::class, 'removeQuestion'])->name('exams.questions.destroy');
        Route::get('exams/{exam}/analytics', [TeacherController::class, 'examAnalytics'])->name('exams.analytics');

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

        Route::post('materials/{material}/flashcards', [TeacherController::class, 'storeFlashcard'])->name('flashcards.store');
        Route::put('flashcards/{flashcard}', [TeacherController::class, 'updateFlashcard'])->name('flashcards.update');
        Route::delete('flashcards/{flashcard}', [TeacherController::class, 'destroyFlashcard'])->name('flashcards.destroy');
        Route::post('materials/{material}/questions', [TeacherController::class, 'storeQuestion'])->name('questions.store');
        Route::put('questions/{question}', [TeacherController::class, 'updateQuestion'])->name('questions.update');
        Route::delete('questions/{question}', [TeacherController::class, 'destroyQuestion'])->name('questions.destroy');

        Route::get('question-bank', [TeacherController::class, 'questionBankIndex'])->name('question-bank.index');
        Route::get('question-bank/{subject}', [TeacherController::class, 'questionBankShow'])->name('question-bank.show');
        Route::post('question-bank', [TeacherController::class, 'questionBankStore'])->name('question-bank.store');
        Route::put('question-bank/{qb}', [TeacherController::class, 'questionBankUpdate'])->name('question-bank.update');
        Route::delete('question-bank/{qb}', [TeacherController::class, 'questionBankDestroy'])->name('question-bank.destroy');
    });

/*
|--------------------------------------------------------------------------
| Student
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.user', '2fa', 'role:student,admin'])
    ->prefix('student')->name('student.')->group(function () {
        Route::get('dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
        // Students work by subject, not by class arm: the arm is how the school
        // groups them, the subject is what they actually study.
        Route::get('subjects', [StudentController::class, 'subjects'])->name('subjects');
        Route::get('subjects/{subject}', [StudentController::class, 'subjectShow'])->name('subjects.show');

        Route::get('exams', [StudentController::class, 'exams'])->name('exams');
        Route::post('exams/{exam}/start', [StudentController::class, 'startExam'])->name('exams.start');
        Route::get('exams/{exam}/take/{attempt}', [StudentController::class, 'takeExam'])->name('exams.take');
        Route::post('exams/{exam}/attempt/{attempt}', [StudentController::class, 'submitExam'])->name('exams.submit');
        Route::get('exams/{exam}/result/{attempt}', [StudentController::class, 'examResult'])->name('exams.result');

        Route::get('study', [StudentController::class, 'studyIndex'])->name('study.index');
        Route::get('study/session', [StudentController::class, 'studySession'])->name('study.session');
        Route::get('study/session/{material}', [StudentController::class, 'studySession'])->name('study.session.material');
        Route::get('study/{material}', [StudentController::class, 'studyHub'])->name('study.hub');
        Route::post('study/{flashcard}/answer', [StudentController::class, 'studyAnswer'])->name('study.answer');
    });

/*
|--------------------------------------------------------------------------
| Materials — shared across roles
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'school.user', '2fa'])->prefix('materials')->name('materials.')->group(function () {
    Route::get('{material}', [MaterialController::class, 'show'])
        ->middleware('role:teacher,student,admin')->name('show');

    Route::post('{material}/generate', [MaterialController::class, 'generate'])
        ->middleware('role:teacher,admin')->name('generate');
    Route::get('jobs/{job}', [MaterialController::class, 'jobStatus'])
        ->middleware('role:teacher,admin')->name('job.status');
});

/*
|--------------------------------------------------------------------------
| Learning — upload, AI generation and the review workflow
|--------------------------------------------------------------------------
|
| Teachers upload and submit; admins approve, request changes or reject. Both
| roles share the material page — the policy decides which controls render, so
| there is no separate admin copy of the same screen.
|
*/
Route::middleware(['auth', 'school.user', '2fa'])
    ->prefix('learning')
    ->name('learning.')
    ->group(function () {

        // ── Teacher: create and generate ──
        Route::middleware('role:teacher,admin')->group(function () {
            Route::post('materials/{material}/regenerate', [MaterialGenerationController::class, 'regenerate'])
                ->name('materials.regenerate');
            Route::post('materials/{material}/submit', [MaterialWorkflowController::class, 'submit'])
                ->name('materials.submit');
        });

        // ── Shared: the material's review page ──
        Route::get('materials/{material}', [MaterialWorkflowController::class, 'show'])
            ->middleware('role:teacher,admin')->name('materials.show');
        Route::post('materials/{material}/notes', [MaterialWorkflowController::class, 'addNote'])
            ->middleware('role:teacher,admin')->name('materials.notes');

        // ── Admin: sign-off ──
        Route::middleware('role:admin')->group(function () {
            Route::get('review', [MaterialWorkflowController::class, 'queue'])->name('review');
            Route::post('materials/{material}/approve', [MaterialWorkflowController::class, 'approve'])
                ->name('materials.approve');
            Route::post('materials/{material}/request-changes', [MaterialWorkflowController::class, 'requestChanges'])
                ->name('materials.request-changes');
            Route::post('materials/{material}/reject', [MaterialWorkflowController::class, 'reject'])
                ->name('materials.reject');
            Route::post('materials/{material}/publish', [MaterialWorkflowController::class, 'publish'])
                ->name('materials.publish');
            Route::post('materials/{material}/unpublish', [MaterialWorkflowController::class, 'unpublish'])
                ->name('materials.unpublish');
        });
    });
