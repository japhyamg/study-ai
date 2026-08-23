<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Signing in with an admission number.
 *
 * Students are issued an admission number rather than a school email, so the
 * one login field takes either. An admission number is only unique within a
 * school, so it is never matched outside the active tenant.
 */
class StudentLoginTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

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
    }

    private function student(string $admissionNumber, ?string $email = null, ?School $school = null): User
    {
        $school ??= $this->school;

        $user = User::create([
            'school_id' => $school->id,
            'name' => 'Sade Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $school->id,
            'role' => SchoolMember::ROLE_STUDENT,
        ]);

        StudentProfile::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'admission_number' => $admissionNumber,
        ]);

        return $user;
    }

    public function test_a_student_signs_in_with_their_admission_number(): void
    {
        $student = $this->student('STU/2026/014');

        $this->post('/login', [
            'login' => 'STU/2026/014',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($student);
    }

    public function test_the_admission_number_is_not_case_sensitive(): void
    {
        $student = $this->student('stu/2026/014');

        $this->post('/login', [
            'login' => 'STU/2026/014',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($student);
    }

    public function test_staff_still_sign_in_with_their_email(): void
    {
        $teacher = User::create([
            'school_id' => $this->school->id,
            'name' => 'Tunde Teacher',
            'email' => 'tunde@test.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $teacher->id, 'school_id' => $this->school->id,
            'role' => SchoolMember::ROLE_TEACHER,
        ]);

        $this->post('/login', [
            'login' => 'tunde@test.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->student('STU/2026/014');

        $this->post('/login', [
            'login' => 'STU/2026/014',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_an_admission_number_from_another_school_is_refused(): void
    {
        $other = School::create([
            'name' => 'Other School', 'slug' => 'other', 'subdomain' => 'other',
            'status' => School::STATUS_ACTIVE,
        ]);

        // Same number, different school. The active tenant is $this->school.
        $this->student('STU/001', null, $other);

        $this->post('/login', [
            'login' => 'STU/001',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_a_deactivated_student_cannot_sign_in(): void
    {
        $student = $this->student('STU/2026/014');
        $student->update(['is_active' => false]);

        $this->post('/login', [
            'login' => 'STU/2026/014',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
