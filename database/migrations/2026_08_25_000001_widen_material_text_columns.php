<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the columns that hold extracted document text.
 *
 * `materials.content` was TEXT, which MySQL caps at 65,535 *bytes* — not
 * characters, so multi-byte text hits the limit sooner still. A single
 * textbook chapter comfortably exceeds that, and the insert fails outright
 * rather than truncating.
 *
 * LONGTEXT is the right type for "however much text the document happened to
 * contain". The same applies to the transcript column and to a study guide's
 * markdown body.
 *
 * Note this is separate from what we send to the AI — that is truncated to a
 * few thousand characters by config('ai.input_limits'). We store the whole
 * document so it can be re-processed later with different settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite has no separate TEXT sizes; the change is a MySQL/Postgres
        // concern only and change() would be a no-op there anyway.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('materials', function (Blueprint $table) {
            $table->longText('content')->nullable()->change();
            $table->longText('transcript')->nullable()->change();
        });

        Schema::table('study_guides', function (Blueprint $table) {
            $table->longText('content')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Rows longer than 64KB would be truncated on the way back down, so
        // trim them explicitly first rather than letting MySQL do it silently.
        DB::statement('UPDATE materials SET content = LEFT(content, 65000) WHERE LENGTH(content) > 65000');
        DB::statement('UPDATE materials SET transcript = LEFT(transcript, 65000) WHERE LENGTH(transcript) > 65000');
        DB::statement('UPDATE study_guides SET content = LEFT(content, 65000) WHERE LENGTH(content) > 65000');

        Schema::table('materials', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
            $table->text('transcript')->nullable()->change();
        });

        Schema::table('study_guides', function (Blueprint $table) {
            $table->text('content')->change();
        });
    }
};
