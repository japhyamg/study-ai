<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\TeacherTokenLimit;
use App\Models\TokenUsage;
use App\Models\User;
use App\Services\TokenLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The topbar AI allowance indicator.
 *
 * The allowance is a calendar-month window rather than a stored counter:
 * usage is summed from the 1st, so it resets on its own with no job to run.
 * That is the part most likely to be quietly wrong, so it is pinned here.
 */
class TokenMeterTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'subdomain' => 'test',
            'status' => School::STATUS_ACTIVE,
        ]);

        app()->instance('tenant', $this->school);

        $this->teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => 'Person',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $this->school->id, 'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    private function spend(int $tokens, ?User $user = null, ?string $at = null): void
    {
        $row = TokenUsage::create([
            'school_id' => $this->school->id,
            'user_id' => ($user ?? $this->teacher)->id,
            'operation' => 'generate',
            'model' => 'test/model',
            'prompt_tokens' => 0,
            'completion_tokens' => $tokens,
            'total_tokens' => $tokens,
            'cost' => 0,
        ]);

        if ($at) {
            $row->forceFill(['created_at' => $at])->saveQuietly();
        }

        app(TokenLimitService::class)->forget(($user ?? $this->teacher)->id);
    }

    private function limit(int $monthly, bool $enabled = true): void
    {
        app(TokenLimitService::class)->setTeacherTokenLimit($this->teacher->id, $monthly, $enabled);
    }

    // ───────────────────────── the monthly window ─────────────────────────

    /** The central claim: last month's spend does not count against this month. */
    public function test_spend_from_a_previous_month_is_excluded(): void
    {
        $this->spend(5000, at: now()->subMonthNoOverflow()->startOfMonth()->toDateTimeString());
        $this->spend(1000);

        $this->assertSame(
            1000,
            app(TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id)['usedThisMonth']
        );
    }

    public function test_spend_on_the_first_of_the_month_counts(): void
    {
        $this->spend(2500, at: now()->startOfMonth()->toDateTimeString());

        $this->assertSame(
            2500,
            app(TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id)['usedThisMonth']
        );
    }

    public function test_another_users_spend_is_not_counted(): void
    {
        $other = $this->user('other@test.test', SchoolMember::ROLE_TEACHER);

        $this->spend(9000, user: $other);
        $this->spend(1000);

        $this->assertSame(
            1000,
            app(TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id)['usedThisMonth']
        );
    }

    // ───────────────────────── what the topbar shows ─────────────────────────

    public function test_a_teacher_sees_the_indicator(): void
    {
        $this->limit(100000);
        $this->spend(25000);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('AI tokens this month');
    }

    /** Students cannot generate anything, so an allowance would be noise. */
    public function test_a_student_does_not_see_the_indicator(): void
    {
        $student = $this->user('student@test.test', SchoolMember::ROLE_STUDENT);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('AI tokens this month');
    }

    public function test_the_indicator_reports_the_percentage_used(): void
    {
        $this->limit(1000);
        $this->spend(750);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('75%');
    }

    public function test_a_used_up_allowance_says_generation_will_fail(): void
    {
        $this->limit(1000);
        $this->spend(1000);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Allowance used');
    }

    /** An unenforced allowance is information, not a warning. */
    public function test_a_disabled_limit_is_shown_as_unlimited(): void
    {
        $this->limit(1000, enabled: false);
        $this->spend(5000);

        $this->actingAs($this->teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('No limit is being enforced');
    }

    // ───────────────────────── the cache ─────────────────────────

    /**
     * The indicator renders on every page load, so the figures are cached —
     * but they must not look stuck after the teacher's own generation.
     */
    public function test_recording_spend_clears_the_cached_figures(): void
    {
        $service = app(TokenLimitService::class);

        $this->limit(100000);
        $service->getTeacherTokenLimitCached($this->teacher->id);

        $this->spend(4000);

        $this->assertSame(4000, $service->getTeacherTokenLimitCached($this->teacher->id)['usedThisMonth']);
    }

    public function test_changing_the_limit_clears_the_cached_figures(): void
    {
        $service = app(TokenLimitService::class);

        $this->limit(100000);
        $this->assertSame(100000, $service->getTeacherTokenLimitCached($this->teacher->id)['monthlyLimit']);

        $this->limit(5000);

        $this->assertSame(5000, $service->getTeacherTokenLimitCached($this->teacher->id)['monthlyLimit']);
    }

    // ───────────────────────── attribution still recorded ─────────────────────────

    /** Spend is a historical fact and must survive the material being deleted. */
    public function test_usage_survives_the_material_being_deleted(): void
    {
        $material = Material::create([
            'school_id' => $this->school->id,
            'created_by' => $this->teacher->id,
            'title' => 'Algebra',
            'type' => 'note',
            'content' => 'x',
        ]);

        TokenUsage::create([
            'school_id' => $this->school->id,
            'user_id' => $this->teacher->id,
            'material_id' => $material->id,
            'operation' => 'generate',
            'model' => 'test/model',
            'prompt_tokens' => 0,
            'completion_tokens' => 1000,
            'total_tokens' => 1000,
            'cost' => 0,
        ]);

        $material->delete();

        app(TokenLimitService::class)->forget($this->teacher->id);

        $this->assertSame(
            1000,
            app(TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id)['usedThisMonth']
        );
    }
}
