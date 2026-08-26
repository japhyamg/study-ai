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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A teacher files study material against the subjects they actually teach.
 *
 * The form only offers their own pairs, but the form is not the security
 * boundary — these cover the server-side check, including the case that a
 * naive implementation gets wrong: a teacher who takes Maths in one class and
 * English in another must not be able to file English against the Maths class.
 */
class MaterialAssignmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $teacher;

    private ClassArm $mathsClass;

    private ClassArm $englishClass;

    private Subject $maths;

    private Subject $english;

    private Subject $physics;

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

        $this->mathsClass = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $this->englishClass = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'B',
        ]);

        $this->maths = $this->subject('Maths', 'MTH');
        $this->english = $this->subject('English', 'ENG');
        $this->physics = $this->subject('Physics', 'PHY');

        $this->teacher = $this->user('teacher@test.test', SchoolMember::ROLE_TEACHER);

        // Maths in A, English in B — deliberately crossed.
        $this->assign($this->mathsClass, $this->maths, $this->teacher);
        $this->assign($this->englishClass, $this->english, $this->teacher);
    }

    private function subject(string $name, string $code): Subject
    {
        return Subject::create(['school_id' => $this->school->id, 'name' => $name, 'code' => $code]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::create([
            'school_id' => $this->school->id,
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id, 'school_id' => $this->school->id, 'role' => $role,
        ]);

        return $user->fresh('memberships');
    }

    private function assign(ClassArm $arm, Subject $subject, User $teacher): void
    {
        ClassSubjectAssignment::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $arm->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Algebra basics',
            'content' => str_repeat('Substantive lesson content for the study guide. ', 20),
            'class_arm_id' => $this->mathsClass->id,
            'subject_id' => $this->maths->id,
        ], $overrides);
    }

    // ───────────────────────── the form ─────────────────────────

    public function test_create_form_only_offers_the_teachers_own_subjects(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.materials.create'));

        $response->assertOk();
        $response->assertViewHas('subjects', function ($subjects) {
            return $subjects->pluck('name')->sort()->values()->all() === ['English', 'Maths'];
        });
        $response->assertViewHas('classes', fn ($classes) => $classes->count() === 2);
    }

    public function test_create_form_does_not_offer_a_subject_the_teacher_does_not_teach(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.materials.create'));

        $response->assertViewHas('subjects', fn ($subjects) => ! $subjects->contains('id', $this->physics->id));
    }

    public function test_an_admin_still_sees_every_subject(): void
    {
        $admin = $this->user('admin@test.test', SchoolMember::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get(route('teacher.materials.create'));

        $response->assertViewHas('subjects', fn ($subjects) => $subjects->count() === 3);
        $response->assertViewHas('unassigned', false);
    }

    public function test_a_teacher_with_no_assignments_is_told_rather_than_shown_empty_pickers(): void
    {
        $newTeacher = $this->user('new@test.test', SchoolMember::ROLE_TEACHER);

        $response = $this->actingAs($newTeacher)->get(route('teacher.materials.create'));

        $response->assertViewHas('unassigned', true);
    }

    public function test_the_form_publishes_the_subject_map_for_dependent_filtering(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.materials.create'));

        $response->assertViewHas('subjectsByClass', function ($map) {
            return $map[$this->mathsClass->id] === [$this->maths->id]
                && $map[$this->englishClass->id] === [$this->english->id];
        });
    }

    // ───────────────────────── the server-side check ─────────────────────────

    public function test_a_teacher_can_file_material_against_their_own_pair(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.materials.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Material::count());
    }

    /** The case a naive per-field check would wrongly allow. */
    public function test_a_teacher_cannot_cross_a_subject_into_the_wrong_class(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.materials.store'), $this->payload([
                'class_arm_id' => $this->mathsClass->id,
                'subject_id' => $this->english->id,
            ]))
            ->assertSessionHasErrors('subject_id');

        $this->assertSame(0, Material::count());
    }

    public function test_a_teacher_cannot_file_against_a_subject_they_do_not_teach(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.materials.store'), $this->payload([
                'subject_id' => $this->physics->id,
            ]))
            ->assertSessionHasErrors('subject_id');
    }

    public function test_an_admin_is_not_restricted_by_assignments(): void
    {
        $admin = $this->user('admin@test.test', SchoolMember::ROLE_ADMIN);

        $this->actingAs($admin)
            ->post(route('teacher.materials.store'), $this->payload([
                'subject_id' => $this->physics->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Material::count());
    }

    // ───────────────────────── generation is a separate step ─────────────────────────

    /**
     * Uploading must not start generation. The teacher reviews the extracted
     * text first, then chooses what to generate on the material's own page.
     */
    public function test_creating_a_material_does_not_start_generation(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.materials.store'), $this->payload());

        $material = Material::first();

        $this->assertSame(Material::STATE_DRAFT, $material->workflow_state);
        $this->assertSame(0, $material->processingJobs()->count());
    }

    public function test_the_upload_redirects_to_the_material_page(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.materials.store'), $this->payload())
            ->assertRedirect(route('learning.materials.show', Material::first()));
    }

    public function test_generation_is_refused_when_there_is_no_text(): void
    {
        $material = Material::create([
            'school_id' => $this->school->id,
            'title' => 'Link only',
            'type' => 'link',
            'source_url' => 'https://example.test',
            'status' => Material::STATUS_DRAFT,
            'workflow_state' => Material::STATE_DRAFT,
            'created_by' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)
            ->post(route('learning.materials.regenerate', $material), ['type' => 'generate_all'])
            ->assertSessionHasErrors('type');
    }
}
