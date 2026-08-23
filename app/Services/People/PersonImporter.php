<?php

namespace App\Services\People;

use App\Models\ClassArm;
use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolMember;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bulk import of teachers and students from a CSV file.
 *
 * Every row is validated before anything is written, and the whole import runs
 * in one transaction. A spreadsheet with a mistake on row 40 should not leave
 * 39 people half-created, and an admin cannot easily tell which of them landed.
 */
class PersonImporter
{
    /** Columns each role's file is expected to carry, in order. */
    public const COLUMNS = [
        SchoolMember::ROLE_TEACHER => [
            'name', 'email', 'phone', 'staff_number', 'department', 'qualification', 'password',
        ],
        SchoolMember::ROLE_STUDENT => [
            'name', 'admission_number', 'class', 'email', 'date_of_birth', 'gender',
            'guardian_name', 'guardian_phone', 'guardian_email', 'password',
        ],
    ];

    /** A couple of filled-in rows, so the format is obvious from the file. */
    public const SAMPLES = [
        SchoolMember::ROLE_TEACHER => [
            ['Amina Yusuf', 'amina.yusuf@school.edu', '08031234567', 'STF/001', 'Science', 'B.Ed Biology', ''],
            ['Chidi Okeke', 'chidi.okeke@school.edu', '08039876543', 'STF/002', 'Mathematics', 'M.Sc Mathematics', ''],
        ],
        SchoolMember::ROLE_STUDENT => [
            ['Ngozi Eze', 'STU/2026/001', 'JSS 1 A', '', '2013-04-12', 'female',
                'Mrs Eze', '08021112222', 'ngozi.guardian@example.com', ''],
            ['Tunde Bello', 'STU/2026/002', 'JSS 1 A', '', '2012-11-30', 'male',
                'Mr Bello', '08023334444', '', ''],
        ],
    ];

    /**
     * @return array{created:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, string $role, School $school): array
    {
        $expected = self::COLUMNS[$role] ?? null;

        if ($expected === null) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['Unsupported role.']];
        }

        $rows = $this->read($path, $expected);

        if ($rows === []) {
            return ['created' => 0, 'skipped' => 0, 'errors' => ['The file has no rows.']];
        }

        $errors = [];
        $valid = [];

        // Track within the file as well as against the database: a spreadsheet
        // can repeat an admission number that does not exist yet.
        $seenEmails = [];
        $seenNumbers = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2; // +1 for the header, +1 because humans count from one
            $name = trim((string) ($row['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

            if ($name === '') {
                $errors[] = "Row {$line}: a name is required.";
                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$line}: '{$email}' is not a valid email address.";
                continue;
            }

            if ($email !== '') {
                if (isset($seenEmails[$email])) {
                    $errors[] = "Row {$line}: the email {$email} appears more than once in this file.";
                    continue;
                }

                if (User::where('school_id', $school->id)->where('email', $email)->exists()) {
                    $errors[] = "Row {$line}: someone with the email {$email} is already at this school.";
                    continue;
                }

                $seenEmails[$email] = true;
            }

            if ($role === SchoolMember::ROLE_TEACHER && $email === '') {
                $errors[] = "Row {$line}: teachers need an email address to sign in.";
                continue;
            }

            if ($role === SchoolMember::ROLE_STUDENT) {
                $number = trim((string) ($row['admission_number'] ?? ''));

                if ($number === '') {
                    $errors[] = "Row {$line}: an admission number is required.";
                    continue;
                }

                $key = mb_strtolower($number);

                if (isset($seenNumbers[$key])) {
                    $errors[] = "Row {$line}: the admission number {$number} appears more than once in this file.";
                    continue;
                }

                if (StudentProfile::where('school_id', $school->id)
                    ->whereRaw('LOWER(admission_number) = ?', [$key])->exists()) {
                    $errors[] = "Row {$line}: the admission number {$number} is already in use.";
                    continue;
                }

                $seenNumbers[$key] = true;
            }

            $valid[] = $row;
        }

        // Nothing is written unless the whole file is clean, so a partial import
        // never leaves the admin guessing who was created.
        if ($errors !== []) {
            return ['created' => 0, 'skipped' => count($rows), 'errors' => $errors];
        }

        $arms = ClassArm::with('classLevel')->where('school_id', $school->id)->get();

        DB::transaction(function () use ($valid, $role, $school, $arms) {
            foreach ($valid as $row) {
                $this->createPerson($row, $role, $school, $arms);
            }
        });

        return ['created' => count($valid), 'skipped' => 0, 'errors' => []];
    }

    /** @param \Illuminate\Support\Collection<int,ClassArm> $arms */
    private function createPerson(array $row, string $role, School $school, $arms): void
    {
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        $given = trim((string) ($row['password'] ?? ''));

        $user = User::create([
            'school_id' => $school->id,
            'name' => trim($row['name']),
            'email' => $email !== '' ? $email : null,
            'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
            'password' => Hash::make($given !== '' ? $given : Str::password(12, symbols: false)),
            'is_active' => true,
        ]);

        SchoolMember::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'role' => $role,
        ]);

        if ($role === SchoolMember::ROLE_TEACHER) {
            TeacherProfile::create([
                'user_id' => $user->id,
                'school_id' => $school->id,
                'staff_number' => trim((string) ($row['staff_number'] ?? '')) ?: null,
                'department' => trim((string) ($row['department'] ?? '')) ?: null,
                'qualification' => trim((string) ($row['qualification'] ?? '')) ?: null,
            ]);

            return;
        }

        StudentProfile::create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'admission_number' => trim($row['admission_number']),
            'date_of_birth' => $this->date($row['date_of_birth'] ?? null),
            'gender' => trim((string) ($row['gender'] ?? '')) ?: null,
            'guardian_name' => trim((string) ($row['guardian_name'] ?? '')) ?: null,
            'guardian_phone' => trim((string) ($row['guardian_phone'] ?? '')) ?: null,
            'guardian_email' => trim((string) ($row['guardian_email'] ?? '')) ?: null,
            'enrolled_on' => now(),
        ]);

        // Classes are named in the file the way they read on screen, so match
        // on the displayed name rather than asking an admin to paste uuids.
        $wanted = mb_strtolower(trim((string) ($row['class'] ?? '')));

        if ($wanted === '') {
            return;
        }

        $arm = $arms->first(fn ($a) => mb_strtolower($a->fullName()) === $wanted);

        if ($arm) {
            ClassEnrollment::firstOrCreate([
                'class_arm_id' => $arm->id,
                'user_id' => $user->id,
            ], [
                'role' => SchoolMember::ROLE_STUDENT,
                'enrolled_at' => now(),
            ]);
        }
    }

    /** Dates are typed by hand, so an unreadable one is dropped rather than fatal. */
    private function date(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Read the file into rows keyed by column name.
     *
     * Headers are matched by name rather than position, so a file with the
     * columns reordered still imports. A UTF-8 BOM is stripped: Excel writes
     * one, and it otherwise becomes part of the first header.
     *
     * @return array<int,array<string,string>>
     */
    private function read(string $path, array $expected): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            fclose($handle);

            return [];
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $map = [];

        foreach ($header as $i => $column) {
            $key = str_replace(' ', '_', mb_strtolower(trim((string) $column)));

            if (in_array($key, $expected, true)) {
                $map[$key] = $i;
            }
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            // Skip blank trailing lines, which spreadsheets add freely.
            if ($line === [null] || implode('', array_map(fn ($v) => trim((string) $v), $line)) === '') {
                continue;
            }

            $row = [];

            foreach ($map as $key => $i) {
                $row[$key] = isset($line[$i]) ? (string) $line[$i] : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
