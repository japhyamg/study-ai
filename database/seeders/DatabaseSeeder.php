<?php

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\ClassModel;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\PlatformAdmin;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Platform super-admin (main domain) ──
        $super = User::firstOrCreate(
            ['email' => 'super@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );
        PlatformAdmin::firstOrCreate(['user_id' => $super->id], ['is_owner' => true]);

        // ── A demo school (workspace at {slug}.<central-domain>, e.g. demo.studyai.test) ──
        $school = School::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo School', 'logo' => null]
        );

        // School administrator (school_admins table)
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'School Admin', 'password' => Hash::make('password')]
        );
        SchoolAdmin::firstOrCreate([
            'user_id' => $admin->id,
            'school_id' => $school->id,
        ]);

        // Teacher (teachers table)
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@example.com'],
            ['name' => 'Demo Teacher', 'password' => Hash::make('password')]
        );
        Teacher::firstOrCreate([
            'user_id' => $teacher->id,
            'school_id' => $school->id,
        ], ['staff_no' => 'T-001']);

        // Student (students table)
        $student = User::firstOrCreate(
            ['email' => 'student@example.com'],
            ['name' => 'Demo Student', 'password' => Hash::make('password')]
        );
        Student::firstOrCreate([
            'user_id' => $student->id,
            'school_id' => $school->id,
        ], ['admission_no' => 'S-001', 'level' => 'SS 2']);

        // Demo class (teacher-owned) for student enrollment + published exam/flashcard
        $class = ClassModel::firstOrCreate(
            ['name' => 'Demo Class', 'school_id' => $school->id],
            ['teacher_id' => $teacher->id, 'invite_code' => 'DEMO123']
        );

        ClassEnrollment::firstOrCreate(
            ['class_id' => $class->id, 'user_id' => $student->id],
            ['role' => 'student']
        );

        // Subjects & Terms (academic structure)
        $subject = Subject::firstOrCreate(
            ['name' => 'Mathematics', 'school_id' => $school->id],
            ['code' => 'MATH']
        );
        Term::firstOrCreate(
            ['name' => 'Fall Term', 'school_id' => $school->id],
            ['active' => true, 'start_date' => now()->startOfMonth(), 'end_date' => now()->addMonths(3)]
        );

        // A question-bank entry
        QuestionBank::firstOrCreate(
            ['question' => 'What is the derivative of x^2?', 'school_id' => $school->id],
            ['subject_id' => $subject->id, 'type' => 'short_answer', 'answer' => '2x', 'explanation' => 'Power rule: d/dx(x^n) = n·x^(n-1).', 'difficulty' => 2, 'created_by' => $teacher->id]
        );

        // A published exam with one question (so the student can take it)
        $exam = Exam::firstOrCreate(
            ['title' => 'Demo Exam', 'school_id' => $school->id],
            ['class_id' => $class->id, 'status' => Exam::STATUS_PUBLISHED, 'duration' => 10, 'pass_mark' => 50, 'created_by' => $teacher->id]
        );
        if ($exam->questions()->count() === 0) {
            ExamQuestion::create([
                'exam_id' => $exam->id,
                'question' => 'What is 2 + 2?',
                'type' => 'mcq',
                'options' => ['3', '4', '5'],
                'answer' => '1',
                'points' => 1,
                'order' => 1,
            ]);
        }

        // A flashcard for the student
        Flashcard::firstOrCreate(
            ['user_id' => $student->id, 'front' => 'Capital of France?'],
            ['back' => 'Paris', 'review_status' => 'pending', 'ease_factor' => 2.5, 'interval' => 0, 'repetitions' => 0, 'due_date' => now()]
        );

        // A material (for AI generation + study views)
        $material = \App\Models\Material::firstOrCreate(
            ['title' => 'Demo Material', 'school_id' => $school->id],
            ['class_id' => $class->id, 'type' => 'note', 'content' => 'The quadratic formula solves ax^2+bx+c=0: x = (-b ± √(b²-4ac))/2a. A derivative measures instantaneous rate of change.', 'status' => \App\Models\Material::STATUS_DRAFT, 'review_status' => \App\Models\Material::REVIEW_PENDING, 'published' => false, 'created_by' => $teacher->id]
        );
    }
}
