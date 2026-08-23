<?php

/*
|--------------------------------------------------------------------------
| Academic presets
|--------------------------------------------------------------------------
|
| The schema itself is country-agnostic. These presets are only the defaults
| a brand-new school is bootstrapped with — an administrator can change or
| delete any of it afterwards.
|
| Pick the active preset with ACADEMIC_PRESET in .env. Add your own by
| copying a block and adjusting the values.
|
*/

return [

    'preset' => env('ACADEMIC_PRESET', 'nigeria'),

    'presets' => [

        /*
        |----------------------------------------------------------------
        | Nigeria — 3-term session, Nursery/Primary/JSS/SS, WAEC grading
        |----------------------------------------------------------------
        */
        'nigeria' => [

            'terms' => [
                ['name' => 'First Term',  'sequence' => 1],
                ['name' => 'Second Term', 'sequence' => 2],
                ['name' => 'Third Term',  'sequence' => 3],
            ],

            // Session runs Sep → Jul
            'session_start_month' => 9,

            'levels' => [
                ['name' => 'Nursery 1', 'code' => 'nur1', 'stage' => 'nursery'],
                ['name' => 'Nursery 2', 'code' => 'nur2', 'stage' => 'nursery'],
                ['name' => 'Primary 1', 'code' => 'pry1', 'stage' => 'primary'],
                ['name' => 'Primary 2', 'code' => 'pry2', 'stage' => 'primary'],
                ['name' => 'Primary 3', 'code' => 'pry3', 'stage' => 'primary'],
                ['name' => 'Primary 4', 'code' => 'pry4', 'stage' => 'primary'],
                ['name' => 'Primary 5', 'code' => 'pry5', 'stage' => 'primary'],
                ['name' => 'Primary 6', 'code' => 'pry6', 'stage' => 'primary'],
                ['name' => 'JSS 1',     'code' => 'jss1', 'stage' => 'junior_secondary'],
                ['name' => 'JSS 2',     'code' => 'jss2', 'stage' => 'junior_secondary'],
                ['name' => 'JSS 3',     'code' => 'jss3', 'stage' => 'junior_secondary'],
                ['name' => 'SS 1',      'code' => 'ss1',  'stage' => 'senior_secondary'],
                ['name' => 'SS 2',      'code' => 'ss2',  'stage' => 'senior_secondary'],
                ['name' => 'SS 3',      'code' => 'ss3',  'stage' => 'senior_secondary'],
            ],

            'assessment_types' => [
                ['name' => 'Continuous Assessment 1', 'code' => 'ca1',  'max_score' => 20,  'weight_percent' => 20],
                ['name' => 'Continuous Assessment 2', 'code' => 'ca2',  'max_score' => 20,  'weight_percent' => 20],
                ['name' => 'Examination',             'code' => 'exam', 'max_score' => 60,  'weight_percent' => 60],
            ],

            'subjects' => [
                ['name' => 'Mathematics',            'code' => 'MTH', 'category' => 'core'],
                ['name' => 'English Language',       'code' => 'ENG', 'category' => 'core'],
                ['name' => 'Basic Science',          'code' => 'BSC', 'category' => 'core',     'applies_to' => ['pry1', 'pry2', 'pry3', 'pry4', 'pry5', 'pry6', 'jss1', 'jss2', 'jss3']],
                ['name' => 'Basic Technology',       'code' => 'BTC', 'category' => 'core',     'applies_to' => ['jss1', 'jss2', 'jss3']],
                ['name' => 'Social Studies',         'code' => 'SOS', 'category' => 'core',     'applies_to' => ['pry1', 'pry2', 'pry3', 'pry4', 'pry5', 'pry6', 'jss1', 'jss2', 'jss3']],
                ['name' => 'Civic Education',        'code' => 'CIV', 'category' => 'core'],
                ['name' => 'Computer Studies',       'code' => 'CMP', 'category' => 'core'],
                ['name' => 'Agricultural Science',   'code' => 'AGR', 'category' => 'elective'],
                ['name' => 'Physics',                'code' => 'PHY', 'category' => 'elective', 'applies_to' => ['ss1', 'ss2', 'ss3']],
                ['name' => 'Chemistry',              'code' => 'CHM', 'category' => 'elective', 'applies_to' => ['ss1', 'ss2', 'ss3']],
                ['name' => 'Biology',                'code' => 'BIO', 'category' => 'elective', 'applies_to' => ['ss1', 'ss2', 'ss3']],
                ['name' => 'Further Mathematics',    'code' => 'FMT', 'category' => 'elective', 'applies_to' => ['ss1', 'ss2', 'ss3']],
                ['name' => 'Economics',              'code' => 'ECO', 'category' => 'elective', 'applies_to' => ['ss1', 'ss2', 'ss3']],
                ['name' => 'Government',             'code' => 'GOV', 'category' => 'elective', 'applies_to' => ['ss1', 'ss2', 'ss3']],
                ['name' => 'Literature in English',  'code' => 'LIT', 'category' => 'elective', 'applies_to' => ['jss1', 'jss2', 'jss3', 'ss1', 'ss2', 'ss3']],
                ['name' => 'Geography',              'code' => 'GEO', 'category' => 'elective'],
                ['name' => 'Yoruba',                 'code' => 'YOR', 'category' => 'elective'],
                ['name' => 'Hausa',                  'code' => 'HAU', 'category' => 'elective'],
                ['name' => 'Igbo',                   'code' => 'IGB', 'category' => 'elective'],
                ['name' => 'Christian Religious Studies', 'code' => 'CRS', 'category' => 'elective'],
                ['name' => 'Islamic Religious Studies',   'code' => 'IRS', 'category' => 'elective'],
            ],

            'streams' => ['Science', 'Arts', 'Commercial'],
        ],

        /*
        |----------------------------------------------------------------
        | Generic — 2 semesters, Year 1–12, simple 40/60 split
        |----------------------------------------------------------------
        */
        'generic' => [
            'terms' => [
                ['name' => 'Semester 1', 'sequence' => 1],
                ['name' => 'Semester 2', 'sequence' => 2],
            ],

            'session_start_month' => 9,

            'levels' => [
                ['name' => 'Year 7',  'code' => 'y7',  'stage' => 'lower_secondary'],
                ['name' => 'Year 8',  'code' => 'y8',  'stage' => 'lower_secondary'],
                ['name' => 'Year 9',  'code' => 'y9',  'stage' => 'lower_secondary'],
                ['name' => 'Year 10', 'code' => 'y10', 'stage' => 'upper_secondary'],
                ['name' => 'Year 11', 'code' => 'y11', 'stage' => 'upper_secondary'],
                ['name' => 'Year 12', 'code' => 'y12', 'stage' => 'upper_secondary'],
            ],

            'assessment_types' => [
                ['name' => 'Coursework',  'code' => 'cw',   'max_score' => 40, 'weight_percent' => 40],
                ['name' => 'Examination', 'code' => 'exam', 'max_score' => 60, 'weight_percent' => 60],
            ],

            'subjects' => [
                ['name' => 'Mathematics', 'code' => 'MTH', 'category' => 'core'],
                ['name' => 'English',     'code' => 'ENG', 'category' => 'core'],
                ['name' => 'Science',     'code' => 'SCI', 'category' => 'core'],
                ['name' => 'History',     'code' => 'HIS', 'category' => 'elective'],
                ['name' => 'Geography',   'code' => 'GEO', 'category' => 'elective'],
                ['name' => 'Computing',   'code' => 'CMP', 'category' => 'elective'],
            ],

            'streams' => [],
        ],
    ],
];
