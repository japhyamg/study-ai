<?php

namespace Tests\Feature\Learning;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
use App\Models\Material;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * An uploaded document is stored as a file and parsed on demand — its text is
 * never copied into the database.
 *
 * That keeps a 900-page textbook out of every row, lets a parser improvement
 * benefit material uploaded before it, and removes the encoding and
 * column-size failures that the upload path used to hit.
 */
class MaterialFileSourceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    private ClassArm $class;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.uploads.disk' => 'local']);
        Storage::fake('local');

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

        $this->class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $this->teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER);

        ClassSubjectAssignment::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => 'Teacher',
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $this->school->id, 'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    private function body(): string
    {
        return str_repeat('Algebra is the branch of mathematics dealing with symbols. ', 20);
    }

    private function upload(?UploadedFile $file = null, array $overrides = []): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.materials.store'), array_merge([
            'title' => 'Basic Algebra',
            'class_arm_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'document' => $file ?? UploadedFile::fake()->createWithContent('algebra.txt', $this->body()),
        ], $overrides));
    }

    // ───────────────── uploaded documents ─────────────────

    public function test_an_uploaded_document_is_stored_on_disk(): void
    {
        $this->upload();

        $material = Material::first();

        $this->assertNotNull($material->file_path);
        Storage::disk('local')->assertExists($material->file_path);
        $this->assertSame('algebra.txt', $material->file_name);
    }

    /** The point of the change: the text stays in the file. */
    public function test_the_extracted_text_is_not_written_to_the_database(): void
    {
        $this->upload();

        $this->assertNull(
            Material::first()->getRawOriginal('content'),
            'Uploaded document text must not be copied into the materials table.'
        );
    }

    public function test_the_text_is_read_back_from_the_stored_file_on_demand(): void
    {
        $this->upload();

        $this->assertStringContainsString('Algebra is the branch', Material::first()->sourceText());
    }

    public function test_an_unreadable_upload_is_rejected_before_anything_is_stored(): void
    {
        // A run of low control bytes is what a scanned PDF's stream looks like.
        $binary = str_repeat(implode('', array_map('chr', range(0, 31))), 40);

        $this->actingAs($this->teacher)
            ->post(route('teacher.materials.store'), [
                'title' => 'Scan',
                'class_arm_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'document' => UploadedFile::fake()->createWithContent('scan.txt', $binary),
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame(0, Material::count());
    }

    public function test_a_missing_file_yields_no_text_and_an_explanation_rather_than_an_error(): void
    {
        $this->upload();

        $material = Material::first();
        Storage::disk('local')->delete($material->file_path);

        $fresh = Material::find($material->id);

        $this->assertSame('', $fresh->sourceText());
        $this->assertStringContainsString('missing from storage', (string) $fresh->sourceTextError());
    }

    public function test_deleting_a_material_removes_its_stored_file(): void
    {
        $this->upload();

        $material = Material::first();
        $path = $material->file_path;

        $material->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_parsing_is_memoised_within_a_request(): void
    {
        $this->upload();

        $material = Material::first();
        $first = $material->sourceText();

        // Removing the file must not change an already-computed answer.
        Storage::disk('local')->delete($material->file_path);

        $this->assertSame($first, $material->sourceText());
    }

    // ───────────────── pasted text still uses the column ─────────────────

    public function test_pasted_text_is_stored_in_the_database_as_before(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.materials.store'), [
            'title' => 'Pasted notes',
            'class_arm_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'content' => $this->body(),
        ]);

        $material = Material::first();

        $this->assertNull($material->file_path);
        $this->assertStringContainsString('Algebra is the branch', $material->getRawOriginal('content'));
        $this->assertStringContainsString('Algebra is the branch', $material->sourceText());
    }

    public function test_editing_content_is_ignored_for_a_file_backed_material(): void
    {
        $this->upload();

        $material = Material::first();

        $this->actingAs($this->teacher)->put(route('teacher.materials.update', $material), [
            'title' => 'Basic Algebra',
            'content' => 'This edit should have no effect.',
        ]);

        $this->assertNull($material->fresh()->getRawOriginal('content'));
        $this->assertStringContainsString('Algebra is the branch', Material::find($material->id)->sourceText());
    }
}
