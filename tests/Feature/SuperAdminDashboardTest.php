<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolMember;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The super-admin area had no test coverage, which is how the same class of
 * bug shipped twice: a school-user method being called on a SuperAdmin.
 *
 * The trap is that Laravel's auth middleware calls shouldUse() on whichever
 * guard authenticated the request. On these routes that makes `superadmin`
 * the *default* guard, so a bare auth()->user() returns a SuperAdmin — and
 * `?->` is no protection, because the value is not null, it is the wrong
 * type. Anything shared between the two principals must therefore resolve
 * each from its own guard explicitly.
 */
class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'name' => 'Platform Owner',
            'email' => 'super@studyai.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function schoolAdmin(): User
    {
        $school = School::create([
            'name' => 'Lincoln High',
            'slug' => 'lincoln',
            'subdomain' => 'lincoln',
            'status' => School::STATUS_ACTIVE,
        ]);

        $user = User::create([
            'school_id' => $school->id,
            'name' => 'School Admin',
            'email' => 'admin@lincoln.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'role' => SchoolMember::ROLE_ADMIN,
        ]);

        return $user->fresh('memberships');
    }

    // ───────────────────────── the regression ─────────────────────────

    /** Previously a 500: BadMethodCallException SuperAdmin::roleInSchool(). */
    public function test_the_dashboard_renders_for_a_super_admin(): void
    {
        $this->actingAs($this->superAdmin(), 'superadmin')
            ->get('/super-admin')
            ->assertOk();
    }

    public function test_the_dashboard_shows_the_super_admin_identity(): void
    {
        $this->actingAs($this->superAdmin(), 'superadmin')
            ->get('/super-admin')
            ->assertOk()
            ->assertSee('Platform Owner')
            ->assertSee('Super Admin');
    }

    /**
     * The layout must not treat the super-admin as a school user: no role
     * badge, no school switcher, no tenant-only chrome.
     */
    public function test_the_layout_does_not_render_school_chrome_for_a_super_admin(): void
    {
        $this->actingAs($this->superAdmin(), 'superadmin')
            ->get('/super-admin')
            ->assertOk()
            ->assertDontSee('Sessions &amp; terms', false)
            ->assertDontSee('My classes');
    }

    // ───────────────────────── guard separation ─────────────────────────

    public function test_a_school_user_cannot_reach_the_platform_dashboard(): void
    {
        $response = $this->actingAs($this->schoolAdmin(), 'web')->get('/super-admin');

        $this->assertContains($response->getStatusCode(), [302, 403]);
    }

    public function test_a_guest_is_redirected_away_from_the_platform_dashboard(): void
    {
        $this->get('/super-admin')->assertRedirect();
    }

    // ─────────────────── the shared layout, both ways ───────────────────

    /**
     * The same layout serves both principals, so it has to be exercised from
     * both sides — the bug only appeared on one of them.
     */
    public function test_the_shared_layout_still_renders_for_a_school_user(): void
    {
        $response = $this->actingAs($this->schoolAdmin(), 'web')->get('/admin/dashboard');

        // Tenant resolution depends on the request host, so a redirect is an
        // acceptable outcome here. What must never happen is a 500 — that
        // would mean the layout broke for the other principal.
        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            'The shared layout must not error for a school user.'
        );
    }
}
