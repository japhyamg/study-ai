<?php

namespace App\Providers;

use App\Models\ClassArm;
use App\Models\Exam;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\Question;
use App\Policies\ClassPolicy;
use App\Policies\ExamPolicy;
use App\Policies\FlashcardPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\QuestionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends AuthServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ClassArm::class => ClassPolicy::class,
        Material::class => MaterialPolicy::class,
        Exam::class => ExamPolicy::class,
        Flashcard::class => FlashcardPolicy::class,
        Question::class => QuestionPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One short reference per request, shared by the log line and the
        // error page so a user can quote six characters instead of a stack
        // trace. Resolved lazily: most requests never need it.
        $this->app->singleton('error.reference', fn () => strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route parameters whose names don't match their model.
        \Illuminate\Support\Facades\Route::model('session', \App\Models\AcademicSession::class);
        \Illuminate\Support\Facades\Route::model('level', \App\Models\ClassLevel::class);
        \Illuminate\Support\Facades\Route::model('class', \App\Models\ClassArm::class);

        $this->registerPolicies();

        // super_admin bypasses all policy checks (platform-wide access)
        Gate::before(function ($user, $ability) {
            if ($user instanceof \App\Models\User && $user->isSuperAdmin()) {
                return true;
            }
            return null;
        });
    }
}
