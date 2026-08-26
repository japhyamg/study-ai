<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute token spend to the material that caused it.
 *
 * Usage was recorded per user and per school, which answers "how much has this
 * teacher spent" but not "what did it go on". A teacher looking at their
 * allowance needs the second question answered — a total with no breakdown
 * gives them nothing to act on.
 *
 * Nullable and ON DELETE SET NULL: spend is a historical fact and must survive
 * the material being deleted, otherwise a teacher could clear their own usage
 * by tidying up. Rows written before this migration simply have no material.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_usage', function (Blueprint $table) {
            $table->uuid('material_id')->nullable()->after('user_id');

            $table->foreign('material_id')
                ->references('id')->on('materials')
                ->nullOnDelete();

            // Serves the per-material rollup on the teacher's usage page.
            $table->index(['user_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::table('token_usage', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropIndex(['user_id', 'material_id']);
            $table->dropColumn('material_id');
        });
    }
};
