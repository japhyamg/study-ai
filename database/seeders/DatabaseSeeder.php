<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use App\Models\AssessmentType;
use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\ClassLevel;
use App\Models\ClassSubjectAssignment;
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
use App\Services\Academic\SchoolBootstrapper;
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
                ['Chidi Nwosu', 'chidi@lincoln.test'],
                ['Amara Obi', 'amara@lincoln.test'],
                ['Tunde Balogun', 'tunde@lincoln.test'],
                ['Zainab Yusuf', 'zainab@lincoln.test'],
            ],
            'arms' => [
                ['level' => 'jss1', 'name' => 'A'],
                ['level' => 'jss2', 'name' => 'A'],
            ],
        ]);

        // The same person can hold an account at a second school.
        $this->seedSchool($riverside, [
            'admin' => ['Peter Mensah', 'admin@riverside.test'],
            'teachers' => [
                ['Ngozi Kalu', 'ngozi@riverside.test', 'Humanities'],
            ],
            'students' => [
                ['Chidi Nwosu', 'chidi@riverside.test'],
                ['Ibrahim Sani', 'ibrahim@riverside.test'],
            ],
            'arms' => [
                ['level' => 'ss1', 'name' => 'Blue', 'stream' => 'Science'],
            ],
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
        // Sessions, terms, class levels, subjects and assessment components
        // all come from the configured preset (Nigerian by default).
        app(SchoolBootstrapper::class)->bootstrap($school);
        $school->refresh();

        $term = $school->currentTerm;
        $session = $school->currentSession;

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
                    'hired_on' => now()->subYears(random_int(1, 8)),
                    'employment_type' => 'full_time',
                ]
            );
            $teachers[] = $teacher;
        }

        // ── Class arms ──
        $arms = [];
        foreach ($spec['arms'] as $i => $armSpec) {
            $level = ClassLevel::where('school_id', $school->id)
                ->where('code', $armSpec['level'])
                ->first();

            if (! $level) {
                continue;
            }

            $arms[] = ClassArm::firstOrCreate(
                ['class_level_id' => $level->id, 'name' => $armSpec['name']],
                [
                    'school_id' => $school->id,
                    'academic_session_id' => $session?->id,
                    'form_teacher_id' => $teachers[$i % max(1, count($teachers))]->id ?? null,
                    'stream' => $armSpec['stream'] ?? null,
                    'capacity' => 40,
                ]
            );
        }

        if (empty($arms)) {
            return;
        }

        $primaryArm = $arms[0];

        // ── Students ──
        $students = [];
        foreach ($spec['students'] as $i => [$name, $email]) {
            $student = $this->user($school, $name, $email);
            $this->member($student, $school, SchoolMember::ROLE_STUDENT);

            StudentProfile::firstOrCreate(
                ['user_id' => $student->id, 'school_id' => $school->id],
                [
                    'admission_number' => sprintf('%s/%03d', strtoupper(substr($school->subdomain, 0, 3)), $i + 1),
                    'grade_level' => $primaryArm->classLevel?->name,
                    'section' => $primaryArm->name,
                    'date_of_birth' => now()->subYears(random_int(12, 17))->subDays(random_int(0, 300)),
                    'gender' => $i % 2 === 0 ? 'male' : 'female',
                    'guardian_name' => 'Guardian of '.explode(' ', $name)[0],
                    'guardian_phone' => '+23480'.random_int(10000000, 99999999),
                    'enrolled_on' => now()->subMonths(random_int(2, 20)),
                    'status' => StudentProfile::STATUS_ACTIVE,
                ]
            );

            ClassEnrollment::firstOrCreate(
                ['class_arm_id' => $primaryArm->id, 'user_id' => $student->id],
                ['role' => 'student', 'enrolled_at' => now()->subWeeks(random_int(1, 10))]
            );

            $students[] = $student;
        }

        // ── Subject → teacher assignments for the primary arm ──
        $levelCode = $primaryArm->classLevel?->code ?? '';
        $subjects = Subject::where('school_id', $school->id)
            ->active()
            ->get()
            ->filter(fn (Subject $s) => $s->appliesToLevel($levelCode))
            ->take(6)
            ->values();

        foreach ($subjects as $i => $subject) {
            if (empty($teachers)) {
                break;
            }

            ClassSubjectAssignment::firstOrCreate(
                ['class_arm_id' => $primaryArm->id, 'subject_id' => $subject->id],
                [
                    'school_id' => $school->id,
                    'teacher_id' => $teachers[$i % count($teachers)]->id,
                ]
            );
        }

        $firstSubject = $subjects->first();

        if (! $firstSubject || empty($teachers)) {
            return;
        }

        // ── Sample content so dashboards are not empty ──
        Material::firstOrCreate(
            ['school_id' => $school->id, 'title' => 'Introduction to '.$firstSubject->name],
            [
                'class_arm_id' => $primaryArm->id,
                'subject_id' => $firstSubject->id,
                'type' => 'note',
                'content' => 'Foundational concepts, worked examples and practice questions.',
                'status' => Material::STATUS_READY,
                'review_status' => Material::REVIEW_APPROVED,
                'published' => true,
                'published_at' => now()->subDays(3),
                'created_by' => $teachers[0]->id,
            ]
        );

        $exam = Exam::firstOrCreate(
            ['school_id' => $school->id, 'title' => $firstSubject->name.' — Continuous Assessment 1'],
            [
                'class_arm_id' => $primaryArm->id,
                'subject_id' => $firstSubject->id,
                'status' => Exam::STATUS_PUBLISHED,
                'duration' => 30,
                'pass_mark' => 50,
                'created_by' => $teachers[0]->id,
            ]
        );

        if ($exam->questions()->count() === 0) {
            foreach ([
                ['What is 7 x 8?', ['54', '56', '58', '64'], '1'],
                ['Which of these numbers is prime?', ['9', '15', '17', '21'], '2'],
                ['What is 15% of 200?', ['20', '25', '30', '35'], '2'],
            ] as $order => [$question, $options, $answer]) {
                ExamQuestion::create([
                    'exam_id' => $exam->id,
                    'question' => $question,
                    'type' => 'mcq',
                    'options' => $options,
                    'answer' => $answer,
                    'points' => 1,
                    'order' => $order + 1,
                ]);
            }
        }

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

        QuestionBank::firstOrCreate(
            ['school_id' => $school->id, 'question' => 'Define photosynthesis.'],
            [
                'subject_id' => $firstSubject->id,
                'type' => 'short_answer',
                'answer' => 'The process by which green plants convert light energy into chemical energy.',
                'difficulty' => 2,
                'created_by' => $teachers[0]->id,
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
