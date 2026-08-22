<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SaaS user separation — one credentials table, one table per user type:
 *
 *   platform_admins → super-admins (main domain)
 *   school_admins   → school administrators
 *   teachers        → teachers
 *   students        → students
 *
 * Plus two-factor authentication columns on `users`.
 *
 * Existing `school_members` rows are migrated into the new tables and the
 * legacy table is dropped, leaving a single source of truth per role.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Super-admins (platform staff, main domain) ──
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->boolean('is_owner')->default(false);
            $table->timestamps();

            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── School administrators ──
        Schema::create('school_admins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });

        // ── Teachers ──
        Schema::create('teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->string('staff_no')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });

        // ── Students ──
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('school_id');
            $table->string('admission_no')->nullable();
            $table->string('level')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'school_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });

        // ── Two-factor authentication on the identity table ──
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        // ── Migrate legacy school_members rows into the per-type tables ──
        if (Schema::hasTable('school_members')) {
            $now = now();

            foreach (DB::table('school_members')->orderBy('created_at')->get() as $member) {
                $base = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $member->user_id,
                    'created_at' => $member->created_at ?? $now,
                    'updated_at' => $member->updated_at ?? $now,
                ];

                switch ($member->role) {
                    case 'super_admin':
                        DB::table('platform_admins')->insertOrIgnore($base + ['is_owner' => false]);
                        break;
                    case 'admin':
                        DB::table('school_admins')->insertOrIgnore($base + ['school_id' => $member->school_id]);
                        break;
                    case 'teacher':
                        DB::table('teachers')->insertOrIgnore($base + ['school_id' => $member->school_id, 'staff_no' => null]);
                        break;
                    case 'student':
                        DB::table('students')->insertOrIgnore($base + ['school_id' => $member->school_id, 'admission_no' => null, 'level' => null]);
                        break;
                }
            }

            Schema::dropIfExists('school_members');
        }
    }

    public function down(): void
    {
        // Best-effort restore of the legacy pivot from the per-type tables.
        if (! Schema::hasTable('school_members')) {
            Schema::create('school_members', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('school_id');
                $table->string('role')->default('student');
                $table->timestamps();

                $table->unique(['user_id', 'school_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            });

            $now = now();

            foreach (DB::table('platform_admins')->get() as $admin) {
                DB::table('school_members')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'user_id' => $admin->user_id,
                    'school_id' => DB::table('schools')->value('id'),
                    'role' => 'super_admin',
                    'created_at' => $admin->created_at ?? $now,
                    'updated_at' => $admin->updated_at ?? $now,
                ]);
            }

            foreach (['school_admins' => 'admin', 'teachers' => 'teacher', 'students' => 'student'] as $table => $role) {
                foreach (DB::table($table)->get() as $row) {
                    DB::table('school_members')->insertOrIgnore([
                        'id' => (string) Str::uuid(),
                        'user_id' => $row->user_id,
                        'school_id' => $row->school_id,
                        'role' => $role,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ]);
                }
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });

        Schema::dropIfExists('students');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('school_admins');
        Schema::dropIfExists('platform_admins');
    }
};
