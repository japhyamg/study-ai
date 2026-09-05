<?php

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\ClassModel;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Flashcard;
use App\Models\Material;
use App\Models\QuestionBank;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Populates the app with a realistic, presentable dataset for demos,
 * screenshots and marketing material.
 *
 * DatabaseSeeder gives a minimal smoke-test fixture (counts of 1), which
 * makes dashboards and analytics look like an empty install. This seeder
 * builds a believable secondary school instead: multiple staff, real class
 * sizes, graded exam attempts and AI usage history.
 *
 *   php artisan db:seed --class=Database\\Seeders\\ShowcaseSeeder
 */
class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $pw = Hash::make('password');

        $school = School::firstOrCreate(
            ['slug' => 'demo-school'],
            ['name' => 'Demo School', 'logo' => null]
        );

        // ---------------------------------------------------------- staff
        $super = $this->user('Super Admin', 'super@example.com', $pw);
        $this->member($super, $school, SchoolMember::ROLE_SUPER_ADMIN);

        $admin = $this->user('School Admin', 'admin@example.com', $pw);
        $this->member($admin, $school, SchoolMember::ROLE_ADMIN);

        $teacher = $this->user('Demo Teacher', 'teacher@example.com', $pw);
        $this->member($teacher, $school, SchoolMember::ROLE_TEACHER);

        $staff = [$teacher];
        foreach ([
            'Adaeze Okonkwo', 'Ibrahim Bello', 'Grace Adeyemi',
            'Samuel Eze', 'Fatima Yusuf',
        ] as $i => $name) {
            $t = $this->user($name, 'teacher' . ($i + 2) . '@example.com', $pw);
            $this->member($t, $school, SchoolMember::ROLE_TEACHER);
            $staff[] = $t;
        }

        // ------------------------------------------------------- students
        $student = $this->user('Demo Student', 'student@example.com', $pw);
        $this->member($student, $school, SchoolMember::ROLE_STUDENT);

        $names = [
            'Chidi Nwosu', 'Aisha Mohammed', 'Tunde Bakare', 'Ngozi Eze',
            'Emeka Obi', 'Halima Sani', 'Yemi Adebayo', 'Blessing Okafor',
            'Musa Danjuma', 'Chioma Iwu', 'Segun Ajayi', 'Amina Lawal',
            'Kelechi Umeh', 'Zainab Bello', 'Femi Ogunleye', 'Uche Nnamdi',
            'Rukayat Salami', 'David Etim', 'Peace Aluko', 'Bashir Garba',
            'Ifeoma Chukwu', 'Tobi Adesina', 'Maryam Idris', 'Victor Anosike',
        ];
        $students = [$student];
        foreach ($names as $i => $name) {
            $s = $this->user($name, 'student' . ($i + 2) . '@example.com', $pw);
            $this->member($s, $school, SchoolMember::ROLE_STUDENT);
            $students[] = $s;
        }

        // ---------------------------------------------- academic structure
        $subjectDefs = [
            ['Mathematics', 'MTH'], ['English Language', 'ENG'],
            ['Physics', 'PHY'], ['Chemistry', 'CHM'],
            ['Biology', 'BIO'], ['Economics', 'ECO'],
        ];
        $subjects = [];
        foreach ($subjectDefs as [$n, $c]) {
            $subjects[$c] = Subject::firstOrCreate(
                ['name' => $n, 'school_id' => $school->id],
                ['code' => $c]
            );
        }

        Term::firstOrCreate(
            ['name' => 'First Term 2026/27', 'school_id' => $school->id],
            ['active' => true, 'start_date' => now()->subMonths(2)->startOfMonth(),
             'end_date' => now()->addMonth()->endOfMonth()]
        );
        Term::firstOrCreate(
            ['name' => 'Third Term 2025/26', 'school_id' => $school->id],
            ['active' => false, 'start_date' => now()->subMonths(8),
             'end_date' => now()->subMonths(5)]
        );

        // ---------------------------------------------------------- classes
        $classDefs = [
            ['Demo Class', 'MTH', 0, 'DEMO123'],
            ['SS2 Mathematics', 'MTH', 1, 'MTH2A1'],
            ['SS1 Physics', 'PHY', 2, 'PHY1B2'],
            ['SS3 Chemistry', 'CHM', 3, 'CHM3C4'],
            ['SS2 Biology', 'BIO', 4, 'BIO2D7'],
            ['SS1 English', 'ENG', 5, 'ENG1E3'],
        ];
        $classes = [];
        foreach ($classDefs as [$name, $sub, $ti, $code]) {
            $c = ClassModel::firstOrCreate(
                ['name' => $name, 'school_id' => $school->id],
                ['teacher_id' => $staff[$ti]->id, 'invite_code' => $code]
            );
            $classes[$name] = $c;
        }

        // Enrol students in spread-out groups so rosters look natural.
        foreach ($classes as $name => $class) {
            $size = $name === 'Demo Class' ? 18 : rand(12, 22);
            foreach (array_slice($students, 0, $size) as $s) {
                ClassEnrollment::firstOrCreate(
                    ['class_id' => $class->id, 'user_id' => $s->id],
                    ['role' => 'student']
                );
            }
        }

        // -------------------------------------------------------- materials
        $materialDefs = [
            ['Demo Material', 'MTH', 'Quadratic Equations & the Discriminant',
             'The quadratic formula solves ax^2+bx+c=0: x = (-b ± √(b²-4ac))/2a. The discriminant b²-4ac determines the nature of the roots: positive gives two distinct real roots, zero gives one repeated root, negative gives two complex roots.'],
            ['Simultaneous Equations', 'MTH', null,
             'Simultaneous equations can be solved by substitution, elimination or graphically. The solution is the point where the two lines intersect.'],
            ['Newton\'s Laws of Motion', 'PHY', null,
             'An object remains at rest or in uniform motion unless acted on by a resultant force. F = ma. Every action has an equal and opposite reaction.'],
            ['The Periodic Table', 'CHM', null,
             'Elements are arranged by increasing atomic number. Groups share valence electron count and therefore similar chemical properties.'],
            ['Photosynthesis', 'BIO', null,
             'Photosynthesis converts light energy into chemical energy: 6CO2 + 6H2O -> C6H12O6 + 6O2, occurring in the chloroplasts.'],
            ['Essay Structure & Argument', 'ENG', null,
             'A strong essay opens with a clear thesis, develops one idea per paragraph with supporting evidence, and closes by returning to the thesis.'],
            ['Demand & Supply', 'ECO', null,
             'Demand falls as price rises; supply rises as price rises. Equilibrium is where the two curves intersect.'],
        ];

        $materials = [];
        $classList = array_values($classes);
        foreach ($materialDefs as $i => [$title, $sub, $desc, $content]) {
            $m = Material::firstOrCreate(
                ['title' => $title, 'school_id' => $school->id],
                [
                    'class_id' => $classList[$i % count($classList)]->id,
                    'subject_id' => $subjects[$sub]->id,
                    'description' => $desc,
                    'type' => 'note',
                    'content' => $content,
                    'status' => 'ready',
                    'review_status' => $i === 0 ? Material::REVIEW_PENDING : 'approved',
                    'published' => $i !== 0,
                    'published_at' => $i !== 0 ? now()->subDays(rand(2, 40)) : null,
                    'created_by' => $staff[$i % count($staff)]->id,
                ]
            );
            $materials[] = $m;
        }

        // AI-generated artefacts hanging off the first material, so the
        // "AI generation" screen shows real counters rather than zeros.
        $demoMaterial = $materials[0];
        $cards = [
            ['What is the quadratic formula?', 'x = (-b ± √(b²-4ac)) / 2a'],
            ['What does the discriminant tell you?', 'The nature and number of the roots.'],
            ['Discriminant > 0 means?', 'Two distinct real roots.'],
            ['Discriminant = 0 means?', 'One repeated real root.'],
            ['Discriminant < 0 means?', 'Two complex conjugate roots.'],
            ['Write the general quadratic.', 'ax² + bx + c = 0, where a ≠ 0.'],
            ['How else can a quadratic be solved?', 'Factorising, completing the square, or graphically.'],
            ['What is the axis of symmetry?', 'x = -b / 2a'],
        ];
        foreach ($cards as $n => [$front, $back]) {
            if (DB::table('flashcards')->where('material_id', $demoMaterial->id)
                ->where('front', $front)->exists()) {
                continue;
            }
            DB::table('flashcards')->insert([
                'id' => (string) Str::uuid7(),
                'user_id' => $student->id,
                'material_id' => $demoMaterial->id,
                'front' => $front,
                'back' => $back,
                'review_status' => 'approved',
                'ease_factor' => 2.5,
                'interval' => $n % 4,
                'repetitions' => $n % 3,
                'due_date' => now()->addDays($n % 5 === 0 ? 0 : rand(0, 6)),
                'review_count' => rand(0, 5),
                'created_at' => now()->subDays(rand(1, 20)),
                'updated_at' => now(),
            ]);
        }

        $qs = [
            ['Solve x² - 5x + 6 = 0.', ['x = 2 or 3', 'x = -2 or -3', 'x = 1 or 6', 'x = 0 or 5'], 0,
             'Factorises to (x-2)(x-3) = 0.'],
            ['What is the discriminant of x² + 4x + 4?', ['0', '4', '8', '16'], 0,
             'b²-4ac = 16 - 16 = 0, so one repeated root.'],
            ['How many real roots when b²-4ac < 0?', ['None', 'One', 'Two', 'Three'], 0,
             'A negative discriminant gives complex roots.'],
            ['The axis of symmetry of ax²+bx+c is:', ['x = -b/2a', 'x = b/2a', 'x = -c/a', 'x = a/2b'], 0,
             'Midway between the two roots.'],
            ['Which method always works for a quadratic?', ['The formula', 'Factorising', 'Guessing', 'Inspection'], 0,
             'The quadratic formula applies to every quadratic.'],
            ['If a = 0, the equation is:', ['Linear', 'Quadratic', 'Cubic', 'Undefined'], 0,
             'Without the x² term it is no longer quadratic.'],
        ];
        foreach ($qs as [$q, $opts, $idx, $exp]) {
            if (DB::table('questions')->where('material_id', $demoMaterial->id)
                ->where('question', $q)->exists()) {
                continue;
            }
            DB::table('questions')->insert([
                'id' => (string) Str::uuid7(),
                'material_id' => $demoMaterial->id,
                'question' => $q,
                'type' => 'multiple-choice',
                'options' => json_encode($opts),
                'correct_idx' => $idx,
                'explanation' => $exp,
                'difficulty' => rand(1, 3),
                'review_status' => 'approved',
                'created_at' => now()->subDays(rand(1, 15)),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('study_guides')->where('material_id', $demoMaterial->id)->exists()) {
            $cols = DB::getSchemaBuilder()->getColumnListing('study_guides');
            $row = [
                'id' => (string) Str::uuid7(),
                'material_id' => $demoMaterial->id,
                'created_at' => now()->subDays(3),
                'updated_at' => now(),
            ];
            if (in_array('content', $cols)) {
                $row['content'] = "# Quadratic Equations\n\n## Key formula\nx = (-b ± √(b²-4ac)) / 2a\n\n## The discriminant\nΔ = b²-4ac determines the roots.\n\n- Δ > 0 — two distinct real roots\n- Δ = 0 — one repeated root\n- Δ < 0 — two complex roots\n\n## Worked example\nSolve x² - 5x + 6 = 0 → (x-2)(x-3) = 0 → x = 2 or x = 3.";
            }
            if (in_array('title', $cols)) {
                $row['title'] = 'Quadratic Equations — Study Guide';
            }
            if (in_array('review_status', $cols)) {
                $row['review_status'] = 'approved';
            }
            DB::table('study_guides')->insert($row);
        }

        // ----------------------------------------------------- question bank
        $bank = [
            ['What is the derivative of x^2?', 'MTH', '2x', 'Power rule: d/dx(x^n) = n·x^(n-1).', 2],
            ['State Newton\'s second law.', 'PHY', 'F = ma', 'Force equals mass times acceleration.', 1],
            ['Define an isotope.', 'CHM', 'Same element, different neutron count.', 'Isotopes share atomic number but differ in mass number.', 2],
            ['What gas is released in photosynthesis?', 'BIO', 'Oxygen', 'Water is split, releasing O2.', 1],
            ['Define opportunity cost.', 'ECO', 'The value of the next best alternative forgone.', 'Central to economic choice.', 2],
            ['Integrate 3x^2 with respect to x.', 'MTH', 'x^3 + C', 'Reverse the power rule and add a constant.', 3],
            ['What is a metaphor?', 'ENG', 'A direct comparison without "like" or "as".', 'Contrast with simile.', 1],
            ['State the law of conservation of energy.', 'PHY', 'Energy cannot be created or destroyed.', 'It only changes form.', 2],
        ];
        foreach ($bank as [$q, $sub, $ans, $exp, $diff]) {
            QuestionBank::firstOrCreate(
                ['question' => $q, 'school_id' => $school->id],
                ['subject_id' => $subjects[$sub]->id, 'type' => 'short_answer',
                 'answer' => $ans, 'explanation' => $exp, 'difficulty' => $diff,
                 'created_by' => $teacher->id]
            );
        }

        // ------------------------------------------------------------ exams
        $examDefs = [
            ['Demo Exam', 'Demo Class', 'MTH', Exam::STATUS_PUBLISHED, 10, 50],
            ['Algebra Mid-Term', 'SS2 Mathematics', 'MTH', Exam::STATUS_PUBLISHED, 45, 50],
            ['Mechanics Test 1', 'SS1 Physics', 'PHY', Exam::STATUS_PUBLISHED, 30, 40],
            ['Organic Chemistry Quiz', 'SS3 Chemistry', 'CHM', Exam::STATUS_DRAFT, 25, 50],
            ['Cell Biology Assessment', 'SS2 Biology', 'BIO', Exam::STATUS_PUBLISHED, 40, 45],
        ];

        $examQs = [
            ['What is 2 + 2?', ['3', '4', '5'], '1'],
            ['Solve for x: 2x + 6 = 14', ['2', '4', '6', '8'], '1'],
            ['Factorise x² - 9.', ['(x-3)(x+3)', '(x-9)(x+1)', '(x-3)²', 'x(x-9)'], '0'],
            ['Simplify (x³)(x⁴).', ['x⁷', 'x¹²', 'x', 'x⁴'], '0'],
            ['What is 15% of 200?', ['15', '30', '45', '20'], '1'],
            ['The gradient of y = 3x + 2 is:', ['2', '3', '5', '-3'], '1'],
        ];

        foreach ($examDefs as [$title, $className, $sub, $status, $dur, $pass]) {
            $exam = Exam::firstOrCreate(
                ['title' => $title, 'school_id' => $school->id],
                [
                    'class_id' => $classes[$className]->id,
                    'subject_id' => $subjects[$sub]->id,
                    'status' => $status,
                    'duration' => $dur,
                    'pass_mark' => $pass,
                    'created_by' => $classes[$className]->teacher_id,
                    'published_at' => $status === Exam::STATUS_PUBLISHED ? now()->subDays(rand(3, 25)) : null,
                ]
            );

            if ($exam->questions()->count() === 0) {
                foreach ($examQs as $order => [$q, $opts, $ans]) {
                    ExamQuestion::create([
                        'exam_id' => $exam->id,
                        'question' => $q,
                        'type' => 'mcq',
                        'options' => $opts,
                        'answer' => $ans,
                        'points' => 1,
                        'order' => $order + 1,
                    ]);
                }
            }

            // Graded attempts, so analytics and results are populated.
            if ($status === Exam::STATUS_PUBLISHED) {
                $max = $exam->questions()->count();
                $roster = array_slice($students, 0, rand(10, 18));
                foreach ($roster as $s) {
                    $exists = DB::table('exam_attempts')
                        ->where('exam_id', $exam->id)->where('user_id', $s->id)->exists();
                    if ($exists) {
                        continue;
                    }
                    $score = rand(max(1, (int) ($max * 0.35)), $max);
                    $pct = round($score / $max * 100, 1);
                    $when = now()->subDays(rand(1, 20));
                    DB::table('exam_attempts')->insert([
                        'id' => (string) Str::uuid7(),
                        'exam_id' => $exam->id,
                        'user_id' => $s->id,
                        'score' => $score,
                        'max_score' => $max,
                        'percentage' => $pct,
                        'passed' => $pct >= $pass,
                        'start_time' => $when,
                        'end_time' => (clone $when)->addMinutes(rand(8, $dur)),
                        'submitted' => true,
                        'answers' => json_encode([]),
                        'created_at' => $when,
                        'updated_at' => $when,
                    ]);
                }
            }
        }

        // ------------------------------------------------- AI usage history
        // Gives the token-usage and cost dashboards a real 30-day trend.
        if (DB::table('token_usage')->count() < 50) {
            $ops = ['generate_flashcards', 'generate_questions',
                    'generate_study_guide', 'generate_images', 'summarise'];
            $rows = [];
            for ($d = 29; $d >= 0; $d--) {
                $day = now()->subDays($d);
                foreach (range(1, rand(2, 7)) as $_) {
                    $prompt = rand(400, 2600);
                    $completion = rand(250, 1900);
                    $total = $prompt + $completion;
                    $rows[] = [
                        'id' => (string) Str::uuid7(),
                        'school_id' => $school->id,
                        'user_id' => $staff[array_rand($staff)]->id,
                        'operation' => $ops[array_rand($ops)],
                        'model' => 'gpt-4o-mini',
                        'prompt_tokens' => $prompt,
                        'completion_tokens' => $completion,
                        'total_tokens' => $total,
                        'cost' => round($total * 0.00000045, 6),
                        'created_at' => (clone $day)->setTime(rand(8, 17), rand(0, 59)),
                        'updated_at' => $day,
                    ];
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('token_usage')->insert($chunk);
            }
        }

        // Per-teacher monthly allowances.
        if (DB::table('teacher_token_limits')->count() === 0) {
            $cols = DB::getSchemaBuilder()->getColumnListing('teacher_token_limits');
            foreach ($staff as $t) {
                $row = ['id' => (string) Str::uuid7(), 'created_at' => now(), 'updated_at' => now()];
                if (in_array('user_id', $cols))       $row['user_id'] = $t->id;
                if (in_array('school_id', $cols))     $row['school_id'] = $school->id;
                if (in_array('monthly_limit', $cols)) $row['monthly_limit'] = 500000;
                if (in_array('limit', $cols))         $row['limit'] = 500000;
                DB::table('teacher_token_limits')->insert($row);
            }
        }

        // AI provider entry, so that screen is not empty either.
        if (DB::table('ai_providers')->count() === 0) {
            $cols = DB::getSchemaBuilder()->getColumnListing('ai_providers');
            $row = ['id' => (string) Str::uuid7(), 'created_at' => now(), 'updated_at' => now()];
            if (in_array('name', $cols))       $row['name'] = 'OpenAI';
            if (in_array('provider', $cols))   $row['provider'] = 'openai';
            if (in_array('base_url', $cols))   $row['base_url'] = 'https://api.openai.com/v1';
            if (in_array('model', $cols))      $row['model'] = 'gpt-4o-mini';
            if (in_array('active', $cols))     $row['active'] = true;
            if (in_array('is_active', $cols))  $row['is_active'] = true;
            if (in_array('enabled', $cols))    $row['enabled'] = true;
            if (in_array('priority', $cols))   $row['priority'] = 1;
            if (in_array('api_key', $cols))    $row['api_key'] = 'sk-••••••••••••••••••••';
            DB::table('ai_providers')->insert($row);
        }
    }

    private function user(string $name, string $email, string $pw): User
    {
        return User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $pw]);
    }

    private function member(User $u, School $s, string $role): void
    {
        SchoolMember::firstOrCreate(
            ['user_id' => $u->id, 'school_id' => $s->id],
            ['role' => $role]
        );
    }
}
