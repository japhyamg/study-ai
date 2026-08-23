<?php

namespace Tests\Feature\Admin;

use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Managing a person: password reset, impersonation and deletion.
 *
 * Impersonation is the sharp one. An admin ends up holding a session that
 * belongs to someone else, so the way back has to survive whatever they do
 * next, and it must never become a way to reach another admin's account.
 */
class ManagePeopleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $teacher;

    private User $student;

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

        $this->admin = $this->person('admin@test.test', SchoolMember::ROLE_ADMIN, 'Ada Admin');
        $this->teacher = $this->person('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');
        $this->student = $this->person(null, SchoolMember::ROLE_STUDENT, 'Sade Student');

        StudentProfile::create([
            'user_id' => $this->student->id,
            'school_id' => $this->school->id,
            'admission_number' => 'STU/001',
        ]);
    }

    private function person(?string $email, string $role, string $name, ?School $school = null): User
    {
        $school ??= $this->school;

        $user = User::create([
            'school_id' => $school->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $school->id, 'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    // ── Password ──

    public function test_a_password_can_be_reset_and_is_shown_once(): void
    {
        $before = $this->teacher->password;

        $this->actingAs($this->admin)
            ->put(route('admin.people.password', $this->teacher))
            ->assertRedirect()
            ->assertSessionHas('credentials');

        $this->assertNotSame($before, $this->teacher->fresh()->password);
    }

    public function test_a_chosen_password_is_used(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.people.password', $this->teacher), ['password' => 'chosen-password'])
            ->assertRedirect();

        $this->assertTrue(Hash::check('chosen-password', $this->teacher->fresh()->password));
    }

    public function test_a_password_cannot_be_reset_across_schools(): void
    {
        $other = School::create([
            'name' => 'Other', 'slug' => 'other', 'subdomain' => 'other',
            'status' => School::STATUS_ACTIVE,
        ]);

        $outsider = $this->person('out@test.test', SchoolMember::ROLE_TEACHER, 'Outsider', $other);

        $this->actingAs($this->admin)
            ->put(route('admin.people.password', $outsider))
            ->assertNotFound();
    }

    // ── Impersonation ──

    public function test_an_admin_can_sign_in_as_a_teacher(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $this->teacher))
            ->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticatedAs($this->teacher);
        $this->assertSame($this->admin->id, session('admin_impersonator_id'));
    }

    public function test_an_admin_can_sign_in_as_a_student(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $this->student))
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($this->student);
    }

    public function test_stopping_returns_the_admin_to_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $this->teacher));

        $this->post(route('admin.impersonate.stop'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session('admin_impersonator_id'));
    }

    public function test_a_second_hop_still_returns_to_the_original_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $this->teacher));

        // Hopping again must not record the teacher as the one to return to.
        $this->post(route('admin.people.impersonate', $this->student));

        $this->assertSame($this->admin->id, session('admin_impersonator_id'));
    }

    public function test_an_administrator_cannot_be_impersonated(): void
    {
        $peer = $this->person('peer@test.test', SchoolMember::ROLE_ADMIN, 'Peer Admin');

        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $peer))
            ->assertSessionHasErrors('member');

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_someone_from_another_school_cannot_be_impersonated(): void
    {
        $other = School::create([
            'name' => 'Other', 'slug' => 'other', 'subdomain' => 'other',
            'status' => School::STATUS_ACTIVE,
        ]);

        $outsider = $this->person('out@test.test', SchoolMember::ROLE_TEACHER, 'Outsider', $other);

        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $outsider))
            ->assertNotFound();

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_a_deactivated_person_cannot_be_impersonated(): void
    {
        $this->teacher->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->post(route('admin.people.impersonate', $this->teacher))
            ->assertSessionHasErrors('member');
    }

    public function test_stopping_without_impersonating_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.impersonate.stop'))
            ->assertForbidden();
    }

    // ── Deletion ──

    public function test_an_account_can_be_deleted(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.people.destroy', $this->teacher))
            ->assertRedirect(route('admin.teachers'));

        $this->assertDatabaseMissing('users', ['id' => $this->teacher->id]);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.people.destroy', $this->admin))
            ->assertSessionHasErrors('member');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_the_last_administrator_cannot_be_deleted(): void
    {
        $peer = $this->person('peer@test.test', SchoolMember::ROLE_ADMIN, 'Peer Admin');

        // Demote the setUp admin so `peer` is the only one left.
        SchoolMember::where('user_id', $this->admin->id)
            ->update(['role' => SchoolMember::ROLE_TEACHER]);

        $this->actingAs($peer)
            ->delete(route('admin.people.destroy', $peer))
            ->assertSessionHasErrors('member');
    }
}
