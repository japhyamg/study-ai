<?php

namespace Tests\Feature\Admin;

use App\Models\School;
use App\Models\SchoolMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin people pages.
 *
 * Teachers, students and administrators are one list filtered by membership
 * role. Membership is what ties a person to a school, so every lookup goes
 * through school_members rather than users on their own.
 */
class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

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

        $this->admin = $this->user('admin@test.test', SchoolMember::ROLE_ADMIN, 'Ada Admin');
    }

    private function user(string $email, string $role, string $name, ?School $school = null): User
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

    public function test_the_teacher_list_loads(): void
    {
        $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');

        $this->actingAs($this->admin)
            ->get(route('admin.teachers'))
            ->assertOk()
            ->assertSee('Tunde Teacher');
    }

    public function test_each_list_shows_only_that_role(): void
    {
        $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');
        $this->user('student@test.test', SchoolMember::ROLE_STUDENT, 'Sade Student');

        $this->actingAs($this->admin)
            ->get(route('admin.teachers'))
            ->assertOk()
            ->assertSee('Tunde Teacher')
            ->assertDontSee('Sade Student');

        $this->actingAs($this->admin)
            ->get(route('admin.students'))
            ->assertOk()
            ->assertSee('Sade Student')
            ->assertDontSee('Tunde Teacher');

        $this->actingAs($this->admin)
            ->get(route('admin.administrators'))
            ->assertOk()
            ->assertSee('Ada Admin')
            ->assertDontSee('Tunde Teacher');
    }

    public function test_people_from_another_school_are_not_listed(): void
    {
        $other = School::create([
            'name' => 'Other School', 'slug' => 'other', 'subdomain' => 'other',
            'status' => School::STATUS_ACTIVE,
        ]);

        $this->user('outsider@test.test', SchoolMember::ROLE_TEACHER, 'Outsider Teacher', $other);

        $this->actingAs($this->admin)
            ->get(route('admin.teachers'))
            ->assertOk()
            ->assertDontSee('Outsider Teacher');
    }

    public function test_search_matches_regardless_of_case(): void
    {
        $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');
        $this->user('other@test.test', SchoolMember::ROLE_TEACHER, 'Bola Bello');

        // ilike is Postgres-only; this has to work on MySQL too.
        $this->actingAs($this->admin)
            ->get(route('admin.teachers', ['search' => 'TUNDE']))
            ->assertOk()
            ->assertSee('Tunde Teacher')
            ->assertDontSee('Bola Bello');
    }

    public function test_a_person_can_be_opened(): void
    {
        $teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');

        $this->actingAs($this->admin)
            ->get(route('admin.people.show', $teacher))
            ->assertOk()
            ->assertSee('Tunde Teacher')
            ->assertSee('teacher@test.test');
    }

    public function test_a_person_from_another_school_cannot_be_opened(): void
    {
        $other = School::create([
            'name' => 'Other School', 'slug' => 'other', 'subdomain' => 'other',
            'status' => School::STATUS_ACTIVE,
        ]);

        $outsider = $this->user('outsider@test.test', SchoolMember::ROLE_TEACHER, 'Outsider', $other);

        // Route binding resolves any uuid, so membership must be checked.
        $this->actingAs($this->admin)
            ->get(route('admin.people.show', $outsider))
            ->assertNotFound();
    }

    public function test_details_and_role_can_be_updated(): void
    {
        $teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');

        $this->actingAs($this->admin)
            ->put(route('admin.people.update', $teacher), [
                'name' => 'Tunde Adebayo',
                'email' => 'tunde@test.test',
                'role' => SchoolMember::ROLE_ADMIN,
                'is_active' => '1',
            ])->assertRedirect();

        $teacher->refresh();

        $this->assertSame('Tunde Adebayo', $teacher->name);
        $this->assertSame('tunde@test.test', $teacher->email);
        $this->assertSame(
            SchoolMember::ROLE_ADMIN,
            SchoolMember::where('user_id', $teacher->id)->first()->role
        );
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $this->user('second@test.test', SchoolMember::ROLE_ADMIN, 'Second Admin');

        $this->actingAs($this->admin)
            ->put(route('admin.people.update', $this->admin), [
                'name' => 'Ada Admin',
                'email' => 'admin@test.test',
                'role' => SchoolMember::ROLE_STUDENT,
            ])->assertSessionHasErrors('role');

        $this->assertSame(
            SchoolMember::ROLE_ADMIN,
            SchoolMember::where('user_id', $this->admin->id)->first()->role
        );
    }

    public function test_the_last_administrator_cannot_be_demoted(): void
    {
        $lone = $this->user('lone@test.test', SchoolMember::ROLE_ADMIN, 'Lone Admin');

        // Demote the setUp admin first so `lone` is the only one left.
        SchoolMember::where('user_id', $this->admin->id)
            ->update(['role' => SchoolMember::ROLE_TEACHER]);

        $this->actingAs($lone)
            ->put(route('admin.people.update', $lone), [
                'name' => 'Lone Admin',
                'email' => 'lone@test.test',
                'role' => SchoolMember::ROLE_TEACHER,
            ])->assertSessionHasErrors('role');
    }

    public function test_deactivating_a_person_is_saved(): void
    {
        $teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');

        // An unticked checkbox sends nothing, so it must be written as false.
        $this->actingAs($this->admin)
            ->put(route('admin.people.update', $teacher), [
                'name' => 'Tunde Teacher',
                'email' => 'teacher@test.test',
                'role' => SchoolMember::ROLE_TEACHER,
            ])->assertRedirect();

        $this->assertFalse((bool) $teacher->fresh()->is_active);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER, 'Tunde Teacher');
        $this->user('taken@test.test', SchoolMember::ROLE_TEACHER, 'Someone Else');

        $this->actingAs($this->admin)
            ->put(route('admin.people.update', $teacher), [
                'name' => 'Tunde Teacher',
                'email' => 'taken@test.test',
                'role' => SchoolMember::ROLE_TEACHER,
            ])->assertSessionHasErrors('email');
    }
}
