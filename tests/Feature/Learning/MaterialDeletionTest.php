<?php

namespace Tests\Feature\Learning;

use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Material;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Deleting a study guide.
 *
 * The route and the policy both existed, but no view ever rendered a delete
 * control — so a teacher could not remove their own material at all. These
 * cover the permission rules the new control relies on.
 */
class MaterialDeletionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $owner;

    private User $otherTeacher;

    private User $admin;

    private ClassArm $class;

    private Subject $subject;

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

        $this->class = ClassArm::create([
            'school_id' => $this->school->id,
            'class_level_id' => $level->id, 'name' => 'A',
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id, 'name' => 'Maths', 'code' => 'MTH',
        ]);

        $this->owner = $this->user('owner@test.test', SchoolMember::ROLE_TEACHER);
        $this->otherTeacher = $this->user('other@test.test', SchoolMember::ROLE_TEACHER);
        $this->admin = $this->user('admin@test.test', SchoolMember::ROLE_ADMIN);
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

    private function material(?User $creator = null, string $state = Material::STATE_DRAFT): Material
    {
        return Material::create([
            'school_id' => $this->school->id,
            'class_arm_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'created_by' => ($creator ?? $this->owner)->id,
            'title' => 'Algebra Basics',
            'type' => 'note',
            'content' => 'Some substantive lesson content for the guide.',
            'workflow_state' => $state,
        ]);
    }

    // ───────────────────────── who may delete ─────────────────────────

    public function test_a_teacher_can_delete_their_own_material(): void
    {
        $material = $this->material();

        $this->actingAs($this->owner)
            ->delete(route('teacher.materials.destroy', $material))
            ->assertRedirect(route('teacher.materials.index'));

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    public function test_a_teacher_cannot_delete_another_teachers_material(): void
    {
        $material = $this->material();

        $this->actingAs($this->otherTeacher)
            ->delete(route('teacher.materials.destroy', $material))
            ->assertForbidden();

        $this->assertDatabaseHas('materials', ['id' => $material->id]);
    }

    public function test_an_admin_can_delete_any_material_in_their_school(): void
    {
        $material = $this->material();

        $this->actingAs($this->admin)
            ->delete(route('teacher.materials.destroy', $material));

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    // ───────────────────────── the control is reachable ─────────────────────────

    /** The original defect: the action existed but nothing rendered it. */
    public function test_the_list_offers_a_delete_control_to_the_owner(): void
    {
        $material = $this->material();

        $this->actingAs($this->owner)
            ->get(route('teacher.materials.index'))
            ->assertOk()
            ->assertSee(route('teacher.materials.destroy', $material), false);
    }

    public function test_the_list_hides_the_delete_control_from_other_teachers(): void
    {
        $material = $this->material();

        $this->actingAs($this->otherTeacher)
            ->get(route('teacher.materials.index'))
            ->assertOk()
            ->assertDontSee(route('teacher.materials.destroy', $material), false);
    }

    // ───────────────────────── consequences ─────────────────────────

    /** A published guide can still be removed, but the UI warns first. */
    public function test_a_published_material_can_be_deleted_by_its_owner(): void
    {
        $material = $this->material(state: Material::STATE_PUBLISHED);

        $this->actingAs($this->owner)
            ->delete(route('teacher.materials.destroy', $material));

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    }

    public function test_deleting_a_material_removes_its_generated_content(): void
    {
        $material = $this->material();

        $material->studyGuide()->create([
            'title' => 'Guide',
            'summary' => 'A summary.',
        ]);

        $this->actingAs($this->owner)
            ->delete(route('teacher.materials.destroy', $material));

        $this->assertDatabaseMissing('study_guides', ['material_id' => $material->id]);
    }
}
