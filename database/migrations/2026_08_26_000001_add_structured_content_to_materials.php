<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache the structured form of a material's text.
 *
 * The raw extraction is recovered from the stored file on demand, but turning
 * it into sections is deterministic work that would otherwise repeat on every
 * generation run — and for an uploaded PDF that means re-parsing the file
 * first. Storing the compact result makes generation cheap and keeps the file
 * as the source of truth: this is a derived cache, and clearing it simply
 * causes a rebuild.
 *
 * The payload is compact by design (short keys, boilerplate removed), so it is
 * a fraction of the raw text even before truncation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->longText('structured_content')->nullable()->after('transcript');
            $table->unsignedInteger('structured_chars')->nullable()->after('structured_content');
            $table->timestamp('structured_at')->nullable()->after('structured_chars');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['structured_content', 'structured_chars', 'structured_at']);
        });
    }
};
