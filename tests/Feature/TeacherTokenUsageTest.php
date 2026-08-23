<?php

namespace Tests\Feature;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Material;
use App\Models\PlatformSetting;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\TeacherTokenLimit;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A teacher seeing their own AI allowance and what it was spent on.
 *
 * The allowance is a calendar-month window, not a stored counter: usage is
 * summed from the 1st, so it resets without anything needing to run. These
 * tests pin that behaviour, since "resets monthly" is the part most likely to
 * be quietly wrong.
 */
class TeacherTokenUsageTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    private User $otherTeacher;

    private Material $material;

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

        $level = ClassLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Year 7', 'code' => 'y7', 'position' => 1,
        ]);

        $class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $this->teacher = $this->user('teacher@test.test');
        $this->otherTeacher = $this->user('other@test.test');

        $this->material = Material::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $class->id,
            'subject_id' => $subject->id,
            'created_by' => $this->teacher->id,
            'title' => 'Basic Algebra',
            'type' => 'note',
            'content' => 'Lesson content.',
        ]);
    }

    private function user(string $email): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => 'Teacher',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id,
            'school_id' => $this->school->id,
            'role' => SchoolMember::ROLE_TEACHER,
        ]);

        return $user->fresh('memberships');
    }

    private function usage(array $overrides = []): TokenUsage
    {
        $row = TokenUsage::create(array_merge([
            'school_id' => $this->school->id,
            'user_id' => $this->teacher->id,
            'material_id' => $this->material->id,
            'operation' => 'generate',
            'model' => 'test/model',
            'prompt_tokens' => 400,
            'completion_tokens' => 600,
            'total_tokens' => 1000,
            'cost' => 0.08,
        ], $overrides));

        // created_at is set by the framework, so back-date explicitly when a
        // test needs spend in a previous month.
        if (isset($overrides['created_at'])) {
            $row->forceFill(['created_at' => $overrides['created_at']])->saveQuietly();
        }

        return $row;
    }

    // ───────────────────────── the page ─────────────────────────

    public function test_a_teacher_can_see_their_allowance(): void
    {
        $this->usage();

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('1,000')          // used
            ->assertSee('Basic Algebra'); // what it went on
    }

    public function test_the_page_works_before_anything_has_been_generated(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('Nothing generated yet this month');
    }

    // ───────────────────────── the monthly window ─────────────────────────

    /** The central claim: last month's spend does not count against this month. */
    public function test_usage_from_a_previous_month_is_excluded(): void
    {
        $this->usage(['total_tokens' => 5000, 'created_at' => now()->subMonthNoOverflow()->startOfMonth()]);
        $this->usage(['total_tokens' => 1000]);

        $limits = app(\App\Services\TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id);

        $this->assertSame(1000, $limits['usedThisMonth']);
    }

    public function test_spend_on_the_first_of_the_month_counts(): void
    {
        $this->usage(['total_tokens' => 2500, 'created_at' => now()->startOfMonth()]);

        $limits = app(\App\Services\TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id);

        $this->assertSame(2500, $limits['usedThisMonth']);
    }

    // ───────────────────────── whose usage ─────────────────────────

    public function test_another_teachers_usage_is_not_counted(): void
    {
        $this->usage(['user_id' => $this->otherTeacher->id, 'total_tokens' => 9000]);
        $this->usage(['total_tokens' => 1000]);

        $limits = app(\App\Services\TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id);

        $this->assertSame(1000, $limits['usedThisMonth']);
    }

    public function test_a_teacher_does_not_see_another_teachers_material(): void
    {
        $other = Material::create([
            'school_id' => $this->school->id,
            'created_by' => $this->otherTeacher->id,
            'title' => 'Someone Elses Guide',
            'type' => 'note',
            'content' => 'x',
        ]);

        $this->usage(['user_id' => $this->otherTeacher->id, 'material_id' => $other->id]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertDontSee('Someone Elses Guide');
    }

    // ───────────────────────── the allowance itself ─────────────────────────

    public function test_a_super_admin_override_is_used_instead_of_the_default(): void
    {
        PlatformSetting::updateOrCreate(['key' => 'teacher_default_monthly_limit'], ['value' => '1000000']);

        TeacherTokenLimit::create([
            'user_id' => $this->teacher->id,
            'monthly_limit' => 50000,
            'is_enabled' => true,
        ]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('50,000');
    }

    public function test_running_out_says_when_it_resets(): void
    {
        TeacherTokenLimit::create([
            'user_id' => $this->teacher->id,
            'monthly_limit' => 1000,
            'is_enabled' => true,
        ]);

        $this->usage(['total_tokens' => 1000]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('used your allowance', false)
            ->assertSee(now()->addMonthNoOverflow()->startOfMonth()->format('j F'));
    }

    /** An unenforced allowance still records usage; the page must say so. */
    public function test_a_disabled_limit_is_explained_rather_than_hidden(): void
    {
        TeacherTokenLimit::create([
            'user_id' => $this->teacher->id,
            'monthly_limit' => 1000,
            'is_enabled' => false,
        ]);

        $this->usage(['total_tokens' => 5000]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('not being enforced');
    }

    // ───────────────────────── attribution ─────────────────────────

    public function test_spend_is_grouped_per_material(): void
    {
        $this->usage(['total_tokens' => 1000]);
        $this->usage(['total_tokens' => 500]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('1,500');
    }

    /**
     * Spend is a historical fact. Deleting the material must not erase it, or
     * a teacher could clear their own usage by tidying up.
     */
    public function test_usage_survives_the_material_being_deleted(): void
    {
        $this->usage(['total_tokens' => 1000]);

        $this->material->delete();

        $limits = app(\App\Services\TokenLimitService::class)->getTeacherTokenLimit($this->teacher->id);

        $this->assertSame(1000, $limits['usedThisMonth']);

        $this->actingAs($this->teacher)
            ->get(route('teacher.token-usage'))
            ->assertOk()
            ->assertSee('Other activity');
    }

    // ───────────────────────── access ─────────────────────────

    public function test_a_guest_cannot_see_the_page(): void
    {
        $this->get(route('teacher.token-usage'))->assertRedirect();
    }
}
