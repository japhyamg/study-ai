<?php

namespace Tests\Feature\Admin;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassLevel;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bulk import from a CSV file.
 *
 * The file is checked in full before anything is written. A spreadsheet with a
 * mistake halfway down should not leave half the school created, with no way
 * for the admin to tell which half.
 */
class ImportPeopleTest extends TestCase
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

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('people.csv', $body);
    }

    private function upload(string $role, string $body)
    {
        return $this->actingAs($this->admin)->post(route('admin.people.import.store'), [
            'role' => $role,
            'file' => $this->csv($body),
        ]);
    }

    public function test_the_template_downloads_with_example_rows(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.people.import.template', 'student'))
            ->assertOk();

        $body = $response->streamedContent();

        $this->assertStringContainsString('admission_number', $body);
        // The examples show the shape rather than describing it.
        $this->assertStringContainsString('STU/2026/001', $body);
    }

    public function test_students_are_imported(): void
    {
        $this->upload('student', <<<CSV
        name,admission_number,class,guardian_name
        Ngozi Eze,STU/2026/001,JSS 1 A,Mrs Eze
        Tunde Bello,STU/2026/002,JSS 1 A,Mr Bello
        CSV)->assertRedirect(route('admin.students'));

        $this->assertSame(2, StudentProfile::where('school_id', $this->school->id)->count());

        $ngozi = User::where('name', 'Ngozi Eze')->first();

        $this->assertNotNull($ngozi);
        $this->assertNull($ngozi->email, 'Students need no email address.');
        $this->assertSame(
            SchoolMember::ROLE_STUDENT,
            SchoolMember::where('user_id', $ngozi->id)->first()->role
        );
    }

    public function test_a_student_is_enrolled_in_the_named_class(): void
    {
        $this->upload('student', <<<CSV
        name,admission_number,class
        Ngozi Eze,STU/2026/001,JSS 1 A
        CSV)->assertRedirect();

        $user = User::where('name', 'Ngozi Eze')->first();

        $this->assertTrue(
            ClassEnrollment::where('user_id', $user->id)
                ->where('class_arm_id', $this->class->id)->exists()
        );
    }

    public function test_teachers_are_imported_with_their_profile(): void
    {
        $this->upload('teacher', <<<CSV
        name,email,staff_number,department
        Amina Yusuf,amina@test.test,STF/001,Science
        CSV)->assertRedirect(route('admin.teachers'));

        $user = User::where('email', 'amina@test.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('Science', $user->teacherProfile->department);
    }

    public function test_column_order_does_not_matter(): void
    {
        // Headings are matched by name, so a reordered export still imports.
        $this->upload('student', <<<CSV
        class,name,admission_number
        JSS 1 A,Ngozi Eze,STU/2026/001
        CSV)->assertRedirect();

        $this->assertDatabaseHas('users', ['name' => 'Ngozi Eze']);
    }

    public function test_one_bad_row_stops_the_whole_import(): void
    {
        $this->upload('student', <<<CSV
        name,admission_number
        Ngozi Eze,STU/2026/001
        ,STU/2026/002
        CSV)->assertSessionHas('import_errors');

        // Nothing at all, not just the bad row. Only the admin from setUp remains.
        $this->assertDatabaseMissing('users', ['name' => 'Ngozi Eze']);
        $this->assertSame(0, StudentProfile::count());
    }

    public function test_a_repeated_admission_number_in_the_file_is_caught(): void
    {
        $this->upload('student', <<<CSV
        name,admission_number
        Ngozi Eze,STU/001
        Tunde Bello,stu/001
        CSV)->assertSessionHas('import_errors');

        $this->assertSame(0, StudentProfile::count());
    }

    public function test_an_admission_number_already_in_use_is_caught(): void
    {
        $existing = User::create([
            'school_id' => $this->school->id, 'name' => 'Existing',
            'password' => Hash::make('password'), 'is_active' => true,
        ]);

        StudentProfile::create([
            'user_id' => $existing->id, 'school_id' => $this->school->id,
            'admission_number' => 'STU/001',
        ]);

        $this->upload('student', <<<CSV
        name,admission_number
        Ngozi Eze,STU/001
        CSV)->assertSessionHas('import_errors');
    }

    public function test_a_teacher_row_without_an_email_is_caught(): void
    {
        $this->upload('teacher', <<<CSV
        name,email,staff_number
        Amina Yusuf,,STF/001
        CSV)->assertSessionHas('import_errors');

        $this->assertDatabaseMissing('users', ['name' => 'Amina Yusuf']);
    }

    public function test_a_duplicate_email_is_caught(): void
    {
        $this->upload('teacher', <<<CSV
        name,email
        Amina Yusuf,admin@test.test
        CSV)->assertSessionHas('import_errors');
    }

    public function test_administrators_cannot_be_bulk_imported(): void
    {
        $this->actingAs($this->admin)->post(route('admin.people.import.store'), [
            'role' => 'admin',
            'file' => $this->csv("name,email\nX,x@test.test\n"),
        ])->assertNotFound();
    }

    public function test_a_non_csv_upload_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('admin.people.import.store'), [
            'role' => 'student',
            'file' => UploadedFile::fake()->create('people.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }
}
