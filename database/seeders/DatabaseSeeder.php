<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use App\Models\ClassEnrollment;
use App\Models\ClassModel;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\SuperAdmin;
use App\Models\TeacherProfile;
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
        // ── Platform staff (separate guard, central domain) ──
        SuperAdmin::firstOrCreate(
            ['email' => 'super@studyai.test'],
            [
                'name' => 'Ada Okafor',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // ── Two tenants, to prove isolation ──
        $lincoln = $this->school('Lincoln High School', 'lincoln');
        $riverside = $this->school('Riverside Academy', 'riverside');

        $this->seedSchool($lincoln, [
            'admin' => ['Grace Adeyemi', 'admin@lincoln.test'],
            'teachers' => [
                ['Daniel Eze', 'daniel@lincoln.test', 'Mathematics'],
                ['Fatima Bello', 'fatima@lincoln.test', 'Sciences'],
            ],
            'students' => [
                ['Chidi Nwosu', 'chidi@lincoln.test', 'JSS 2', 'A'],
                ['Amara Obi', 'amara@lincoln.test', 'JSS 2', 'A'],
                ['Tunde Balogun', 'tunde@lincoln.test', 'JSS 2', 'B'],
                ['Zainab Yusuf', 'zainab@lincoln.test', 'JSS 3', 'A'],
            ],
            'subjects' => ['Mathematics' => 'MTH', 'Biology' => 'BIO', 'English' => 'ENG'],
        ]);

        // The same email exists at a second school — allowed, and kept separate.
        $this->seedSchool($riverside, [
            'admin' => ['Peter Mensah', 'admin@riverside.test'],
            'teachers' => [
                ['Ngozi Kalu', 'ngozi@riverside.test', 'Humanities'],
            ],
            'students' => [
                ['Chidi Nwosu', 'chidi@riverside.test', 'Year 10', 'Blue'],
                ['Ibrahim Sani', 'ibrahim@riverside.test', 'Year 10', 'Blue'],
            ],
            'subjects' => ['History' => 'HIS', 'Geography' => 'GEO'],
        ]);
    }

    private function school(string $name, string $subdomain): School
    {
        return School::firstOrCreate(
            ['subdomain' => $subdomain],
            [
                'name' => $name,
                'slug' => $subdomain,
                'status' => School::STATUS_ACTIVE,
                'contact_email' => 'office@'.$subdomain.'.test',
                'timezone' => 'Africa/Lagos',
            ]
        );
    }

    private function seedSchool(School $school, array $spec): void
    {
        // ── Subjects & term ──
        $subjects = [];
        foreach ($spec['subjects'] as $name => $code) {
            $subjects[$name] = Subject::firstOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                ['code' => $code]
            );
        }

        $term = Term::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'First Term'],
            ['active' => true, 'start_date' => now()->startOfMonth(), 'end_date' => now()->addMonths(3)]
        );

        // ── Administrator ──
        [$adminName, $adminEmail] = $spec['admin'];
        $admin = $this->user($school, $adminName, $adminEmail);
        $this->member($admin, $school, SchoolMember::ROLE_ADMIN);
        AdminProfile::firstOrCreate(
            ['user_id' => $admin->id, 'school_id' => $school->id],
            ['job_title' => 'Head of School', 'is_primary' => true, 'staff_number' => 'ADM-001']
        );

        // ── Teachers ──
        $teachers = [];
        foreach ($spec['teachers'] as $i => [$name, $email, $dept]) {
            $teacher = $this->user($school, $name, $email);
            $this->member($teacher, $school, SchoolMember::ROLE_TEACHER);
            TeacherProfile::firstOrCreate(
                ['user_id' => $teacher->id, 'school_id' => $school->id],
                [
                    'staff_number' => sprintf('TCH-%03d', $i + 1),
                    'title' => $i % 2 === 0 ? 'Mr' : 'Mrs',
                    'department' => $dept,
                    'qualification' => 'B.Ed, M.Sc',
                    'hired_on' => now()->subYears(rand(1, 8)),
                    'employment_type' => 'full_time',
                ]
            );
            $teachers[] = $teacher;
        }

        // ── Students ──
        $students = [];
        foreach ($spec['students'] as $i => [$name, $email, $grade, $section]) {
            $student = $this->user($school, $name, $email);
            $this->member($student, $school, SchoolMember::ROLE_STUDENT);
            StudentProfile::firstOrCreate(
                ['user_id' => $student->id, 'school_id' => $school->id],
                [
                    'admission_number' => sprintf('%s/%03d', strtoupper(substr($school->subdomain, 0, 3)), $i + 1),
                    'grade_level' => $grade,
                    'section' => $section,
                    'date_of_birth' => now()->subYears(rand(12, 17))->subDays(rand(0, 300)),
                    'gender' => $i % 2 === 0 ? 'male' : 'female',
                    'guardian_name' => 'Guardian of '.explode(' ', $name)[0],
                    'guardian_phone' => '+234 80'.rand(10000000, 99999999),
                    'enrolled_on' => now()->subMonths(rand(2, 20)),
                    'status' => StudentProfile::STATUS_ACTIVE,
                ]
            );
            $students[] = $student;
        }

        // ── Classes ──
        $firstSubject = reset($subjects);

        foreach ($teachers as $i => $teacher) {
            $class = ClassModel::firstOrCreate(
                ['school_id' => $school->id, 'name' => array_keys($spec['subjects'])[$i % count($spec['subjects'])].' — Set '.($i + 1)],
                [
                    'teacher_id' => $teacher->id,
                    'term_id' => $term->id,
                    'subject_id' => $subjects[array_keys($spec['subjects'])[$i % count($spec['subjects'])]]->id,
                    'invite_code' => strtoupper($school->subdomain).($i + 1).rand(100, 999),
                    'description' => 'Core coursework and continuous assessment.',
                ]
            );

            foreach ($students as $student) {
                ClassEnrollment::firstOrCreate(
                    ['class_id' => $class->id, 'user_id' => $student->id],
                    ['role' => 'student', 'enrolled_at' => now()->subWeeks(rand(1, 10))]
                );
            }

            // Material + exam for the first class only, to keep the seed light.
            if ($i === 0) {
                Material::firstOrCreate(
                    ['school_id' => $school->id, 'title' => 'Introduction to '.$firstSubject->name],
                    [
                        'class_id' => $class->id,
                        'subject_id' => $firstSubject->id,
                        'type' => 'note',
                        'content' => 'Foundational concepts, worked examples and practice questions.',
                        'status' => Material::STATUS_READY,
                        'review_status' => Material::REVIEW_APPROVED,
                        'published' => true,
                        'published_at' => now()->subDays(3),
                        'created_by' => $teacher->id,
                    ]
                );

                $exam = Exam::firstOrCreate(
                    ['school_id' => $school->id, 'title' => $firstSubject->name.' — Continuous Assessment 1'],
                    [
                        'class_id' => $class->id,
                        'status' => Exam::STATUS_PUBLISHED,
                        'duration' => 30,
                        'pass_mark' => 50,
                        'created_by' => $teacher->id,
                    ]
                );

                if ($exam->questions()->count() === 0) {
                    foreach ([
                        ['What is 7 × 8?', ['54', '56', '58', '64'], '1'],
                        ['Which number is prime?', ['9', '15', '17', '21'], '2'],
                        ['What is 15% of 200?', ['20', '25', '30', '35'], '2'],
                    ] as $order => [$q, $options, $answer]) {
                        ExamQuestion::create([
                            'exam_id' => $exam->id,
                            'question' => $q,
                            'type' => 'mcq',
                            'options' => $options,
                            'answer' => $answer,
                            'points' => 1,
                            'order' => $order + 1,
                        ]);
                    }
                }

                // A couple of graded attempts so dashboards aren't empty.
                foreach (array_slice($students, 0, 2) as $n => $student) {
                    ExamAttempt::firstOrCreate(
                        ['exam_id' => $exam->id, 'user_id' => $student->id],
                        [
                            'score' => $n === 0 ? 3 : 2,
                            'max_score' => 3,
                            'percentage' => $n === 0 ? 100 : 67,
                            'passed' => true,
                            'submitted' => true,
                            'start_time' => now()->subDays(2),
                            'end_time' => now()->subDays(2)->addMinutes(18),
                            'answers' => [],
                        ]
                    );
                }
            }
        }

        // ── Question bank & flashcards ──
        QuestionBank::firstOrCreate(
            ['school_id' => $school->id, 'question' => 'Define photosynthesis.'],
            [
                'subject_id' => $firstSubject->id,
                'type' => 'short_answer',
                'answer' => 'The process by which green plants convert light energy into chemical energy.',
                'difficulty' => 2,
                'created_by' => $teachers[0]->id ?? null,
            ]
        );

        foreach ($students as $student) {
            Flashcard::firstOrCreate(
                ['user_id' => $student->id, 'front' => 'What is the capital of Nigeria?'],
                [
                    'back' => 'Abuja',
                    'review_status' => 'pending',
                    'ease_factor' => 2.5,
                    'interval' => 0,
                    'repetitions' => 0,
                    'due_date' => now(),
                ]
            );
        }
    }

    private function user(School $school, string $name, string $email): User
    {
        return User::firstOrCreate(
            ['school_id' => $school->id, 'email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
                'timezone' => 'Africa/Lagos',
            ]
        );
    }

    private function member(User $user, School $school, string $role): void
    {
        SchoolMember::firstOrCreate(
            ['user_id' => $user->id, 'school_id' => $school->id],
            ['role' => $role]
        );
    }
}
