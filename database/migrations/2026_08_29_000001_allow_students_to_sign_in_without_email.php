<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students sign in with an admission number, not an email address.
 *
 * Most secondary school students have no school email, so requiring one meant
 * inventing a fake address to create the account. The column becomes nullable
 * and the per-tenant unique index still holds: several NULLs are allowed
 * alongside it on both MySQL and Postgres, so students without an address do
 * not collide with each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows with no address have to carry one again before the column can be
        // made required, otherwise the change fails on existing data.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
