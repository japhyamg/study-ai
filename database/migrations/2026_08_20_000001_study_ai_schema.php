<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * StudyAI domain schema — ported from schema.prisma + prisma/*.sql.
     * Conventions:
     *  - UUID PKs (default gen_random_uuid())
     *  - enums -> string columns with constant-backed model accessors
     *  - string[] / JSON -> json columns (cast to array in models)
     *  - FK onDelete rules mirror Prisma (Cascade / SetNull)
     */
    public function up(): void
    {
        // ───────────────────────── AUTH & TENANCY ─────────────────────────

        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('school_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->string('role')->default('student'); // super_admin|admin|teacher|student
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });

        // ───────────────────────── ACADEMIC STRUCTURE ─────────────────────────

        Schema::create('terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('term_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('teacher_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('invite_code')->nullable()->unique();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('term_id')->references('id')->on('terms')->onDelete('set null');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_id');
            $table->uuid('user_id');
            $table->string('role')->default('student');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'user_id']);
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('invite_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('code')->unique();
            $table->uuid('class_id')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('set null');
        });

        // ───────────────────────── MATERIALS & AI CONTENT ─────────────────────────

        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('class_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type'); // pdf|pptx|youtube|video|doc|link
            $table->string('source_url')->nullable();
            $table->string('storage_url')->nullable();
            $table->text('content')->nullable();
            $table->text('transcript')->nullable();
            $table->string('status')->default('draft'); // draft|processing|ready|failed
            $table->string('review_status')->default('pending'); // pending|approved|rejected
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('set null');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });

        Schema::create('study_guides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id')->unique();
            $table->text('content');
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });

        Schema::create('flashcards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('material_id')->nullable();
            $table->text('front');
            $table->text('back');
            $table->json('tags')->nullable();
            $table->string('review_status')->default('pending');
            // SM-2 / FSRS spaced repetition
            $table->float('ease_factor')->default(2.5);
            $table->integer('interval')->default(0);
            $table->integer('repetitions')->default(0);
            $table->integer('lapses')->default(0);
            $table->timestamp('due_date')->nullable();
            $table->timestamp('last_review')->nullable();
            $table->integer('review_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'due_date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->text('question');
            $table->string('type')->default('multiple-choice');
            $table->json('options')->nullable();
            $table->integer('correct_idx');
            $table->text('explanation');
            $table->integer('difficulty')->default(1);
            $table->json('tags')->nullable();
            $table->string('review_status')->default('pending');
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });

        Schema::create('material_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->string('storage_url');
            $table->integer('page_number');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('mime_type');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['material_id']);
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name');
            $table->text('content');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ───────────────────────── QUESTION BANK ─────────────────────────

        Schema::create('question_bank', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('subject_id')->nullable();
            $table->text('question');
            $table->string('type')->default('mcq'); // mcq|true_false|fill_blank|short_answer|essay
            $table->json('options')->nullable();
            $table->text('answer');
            $table->text('explanation')->nullable();
            $table->integer('difficulty')->default(1);
            $table->json('tags')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });

        // ───────────────────────── EXAM / CBT ─────────────────────────

        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->uuid('class_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft'); // draft|published|archived
            $table->integer('duration')->nullable(); // minutes
            $table->float('pass_mark')->nullable(); // percentage
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->float('negative_marking')->default(0);
            $table->integer('max_attempts')->default(1);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->boolean('show_results')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('classes')->onDelete('set null');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->text('question');
            $table->string('type')->default('mcq');
            $table->json('options')->nullable();
            $table->text('answer');
            $table->text('explanation')->nullable();
            $table->integer('points')->default(1);
            $table->integer('order')->default(0);
            $table->uuid('bank_id')->nullable();
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->uuid('user_id');
            $table->float('score')->nullable();
            $table->float('max_score')->nullable();
            $table->float('percentage')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->boolean('submitted')->default(false);
            $table->json('answers')->nullable(); // [{questionId,answer,correct?}]
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ───────────────────────── JOB PROCESSING ─────────────────────────

        Schema::create('processing_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // extract_content|generate_flashcards|generate_questions|generate_study_guide|generate_all
            $table->string('status')->default('pending'); // pending|processing|completed|failed
            $table->uuid('school_id');
            $table->uuid('material_id')->nullable();
            $table->uuid('exam_id')->nullable();
            $table->text('input_url')->nullable();
            $table->text('input_text')->nullable();
            $table->integer('progress')->default(0);
            $table->text('error')->nullable();
            $table->json('result')->nullable();
            $table->uuid('created_by');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['created_by']);
        });

        // ───────────────────────── AI CACHE & USAGE ─────────────────────────

        Schema::create('ai_cache', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('content_hash')->unique();
            $table->json('response');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('token_usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('operation'); // generate_flashcards|generate_questions|...
            $table->string('model');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost', 12, 6)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // ───────────────────────── PLATFORM / PROVIDER TABLES ─────────────────────────

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('base_url');
            $table->text('api_key');
            $table->string('model');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('teacher_token_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->integer('monthly_limit')->default(1000000);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });

        // Default teacher limit seed
        DB::table('platform_settings')->insert([
            'key' => 'teacher_default_monthly_limit',
            'value' => '1000000',
        ]);
    }

    public function down(): void
    {
        $tables = [
            'platform_settings', 'teacher_token_limits', 'ai_providers',
            'token_usage', 'ai_cache', 'processing_jobs',
            'exam_attempts', 'exam_questions', 'exams',
            'question_bank', 'topics', 'material_images',
            'questions', 'flashcards', 'study_guides', 'materials',
            'invite_codes', 'class_enrollments', 'classes',
            'subjects', 'terms', 'school_members', 'schools',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
};
