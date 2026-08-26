<?php

namespace Tests\Feature\Admin;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassLevel;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Adding people to a school.
 *
 * Each role has its own form because the details differ: a student carries an
 * admission number, a guardian and a class, a teacher carries a staff number
 * and a department. Students sign in with the admission number, since most of
 * them have no school email address.
 */
class AddPeopleTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private ClassArm $class;

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
            'name' => 'JSS 1', 'code' => 'jss1', 'position' => 1,
        ]);

        $this->class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $this->admin = User::create([
            'school_id' => $this->school->id,
            'name' => 'Ada Admin',
            'email' => 'admin@test.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $this->admin->id,
            'school_id' => $this->school->id,
            'role' => SchoolMember::ROLE_ADMIN,
        ]);

        $this->admin = $this->admin->fresh('memberships');
    }

    public function test_each_role_has_its_own_form(): void
    {
        // The student form asks for an admission number; the staff forms do not.
        $this->actingAs($this->admin)
            ->get(route('admin.students.create'))
            ->assertOk()
            ->assertSee('Admission number')
            ->assertSee('Guardian');

        $this->actingAs($this->admin)
            ->get(route('admin.teachers.create'))
            ->assertOk()
            ->assertSee('Department')
            ->assertDontSee('Admission number');

        $this->actingAs($this->admin)
            ->get(route('admin.administrators.create'))
            ->assertOk()
            ->assertSee('Job title')
            ->assertDontSee('Guardian');
    }

    public function test_a_teacher_is_created_with_a_profile(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => SchoolMember::ROLE_TEACHER,
                'name' => 'Tunde Teacher',
                'email' => 'tunde@test.test',
                'staff_number' => 'STF/01',
                'department' => 'Science',
            ])->assertRedirect(route('admin.teachers'));

        $user = User::where('email', 'tunde@test.test')->first();

        $this->assertNotNull($user);
        $this->assertSame($this->school->id, $user->school_id);
        $this->assertSame(
            SchoolMember::ROLE_TEACHER,
            SchoolMember::where('user_id', $user->id)->first()->role
        );
        $this->assertSame('Science', TeacherProfile::where('user_id', $user->id)->first()->department);
    }

    public function test_a_student_is_created_without_an_email(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => SchoolMember::ROLE_STUDENT,
                'name' => 'Sade Student',
                'admission_number' => 'STU/2026/014',
                'class_arm_id' => $this->class->id,
                'guardian_name' => 'Mrs Student',
            ])->assertRedirect(route('admin.students'));

        $user = User::where('name', 'Sade Student')->first();

        $this->assertNotNull($user, 'A student should not need an email address.');
        $this->assertNull($user->email);

        $profile = StudentProfile::where('user_id', $user->id)->first();
        $this->assertSame('STU/2026/014', $profile->admission_number);
        $this->assertSame('Mrs Student', $profile->guardian_name);

        $this->assertTrue(
            ClassEnrollment::where('user_id', $user->id)
                ->where('class_arm_id', $this->class->id)->exists()
        );
    }

    public function test_a_student_needs_an_admission_number(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => SchoolMember::ROLE_STUDENT,
                'name' => 'Sade Student',
            ])->assertSessionHasErrors('admission_number');

        $this->assertDatabaseMissing('users', ['name' => 'Sade Student']);
    }

    public function test_a_teacher_needs_an_email(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => SchoolMember::ROLE_TEACHER,
                'name' => 'Tunde Teacher',
            ])->assertSessionHasErrors('email');
    }

    public function test_admission_numbers_are_unique_within_a_school(): void
    {
        $payload = [
            'role' => SchoolMember::ROLE_STUDENT,
            'name' => 'First Student',
            'admission_number' => 'STU/001',
        ];

        $this->actingAs($this->admin)->post(route('admin.people.store'), $payload)->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), array_merge($payload, ['name' => 'Second Student']))
            ->assertSessionHasErrors('admission_number');
    }

    public function test_the_generated_password_is_shown_once(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => SchoolMember::ROLE_STUDENT,
                'name' => 'Sade Student',
                'admission_number' => 'STU/2026/014',
            ])
            ->assertRedirect(route('admin.students'))
            ->assertSessionHas('credentials');
    }

    public function test_a_given_password_is_used(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => SchoolMember::ROLE_STUDENT,
                'name' => 'Sade Student',
                'admission_number' => 'STU/2026/014',
                'password' => 'chosen-password',
            ])->assertRedirect();

        $user = User::where('name', 'Sade Student')->first();

        $this->assertTrue(Hash::check('chosen-password', $user->password));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.people.store'), [
                'role' => 'superuser',
                'name' => 'Sneaky',
                'email' => 'sneaky@test.test',
            ])->assertNotFound();
    }
}
