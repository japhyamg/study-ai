<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\ClassArm;
use App\Models\ClassLevel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedScenario(): array
    {
        $school = School::create(['name' => 'T', 'slug' => 't']);
        $teacher = User::create(['name' => 'T', 'email' => 't@e.com', 'password' => bcrypt('x')]);
        $student = User::create(['name' => 'S', 'email' => 's@e.com', 'password' => bcrypt('x')]);
        SchoolMember::create(['user_id' => $teacher->id, 'school_id' => $school->id, 'role' => SchoolMember::ROLE_TEACHER]);
        SchoolMember::create(['user_id' => $student->id, 'school_id' => $school->id, 'role' => SchoolMember::ROLE_STUDENT]);
        $level = ClassLevel::create([
            'school_id' => $school->id, 'name' => 'Year 7', 'code' => 'y7', 'position' => 1,
        ]);
        $class = ClassArm::create([
            'school_id' => $school->id, 'class_level_id' => $level->id,
            'name' => 'A', 'form_teacher_id' => $teacher->id,
        ]);
        ClassEnrollment::create(['class_arm_id' => $class->id, 'user_id' => $student->id, 'role' => 'student']);

        $exam = Exam::create([
            'title' => 'E', 'school_id' => $school->id, 'class_arm_id' => $class->id,
            'status' => Exam::STATUS_PUBLISHED, 'duration' => 10, 'pass_mark' => 50, 'created_by' => $teacher->id,
        ]);
        $q = ExamQuestion::create([
            'exam_id' => $exam->id, 'question' => '2+2?', 'type' => 'mcq',
            'options' => ['3', '4', '5'], 'answer' => '1', 'points' => 1, 'order' => 1,
        ]);
        return [$student, $exam, $q];
    }

    public function test_student_can_take_and_pass_exam(): void
    {
        [$student, $exam, $q] = $this->seedScenario();
        $this->actingAs($student);

        $start = $this->post(route('student.exams.start', $exam));
        $start->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->where('user_id', $student->id)->latest()->first();
        $this->assertNotNull($attempt);
        $this->assertFalse($attempt->submitted);

        $take = $this->get(route('student.exams.take', [$exam, $attempt]));
        $take->assertOk();

        $submit = $this->post(route('student.exams.submit', [$exam, $attempt]), [
            'q' => [$q->id => '1'],
        ]);
        $submit->assertRedirect(route('student.exams.result', [$exam, $attempt]));

        $attempt->refresh();
        $this->assertTrue($attempt->submitted);
        $this->assertEquals(100, $attempt->percentage);
        $this->assertTrue($attempt->passed);
    }

    public function test_student_fails_with_wrong_answer(): void
    {
        [$student, $exam, $q] = $this->seedScenario();
        $this->actingAs($student);
        $this->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::where('exam_id', $exam->id)->latest()->first();

        $this->post(route('student.exams.submit', [$exam, $attempt]), [
            'q' => [$q->id => '0'],
        ]);
        $attempt->refresh();
        $this->assertEquals(0, $attempt->percentage);
        $this->assertFalse($attempt->passed);
    }
}
