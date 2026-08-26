<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give banked questions a provenance.
 *
 * Approved quiz questions are copied into the subject's bank so a teacher can
 * reuse them when building an exam later. Three things follow from that:
 *
 *  - `material_id` records which study guide a question came from, so a
 *    teacher can see the topic it belongs to rather than a flat list of
 *    hundreds of questions.
 *  - `source_question_id` is unique, so re-approving a material (unpublish,
 *    revise, approve again) tops the bank up instead of duplicating it.
 *  - `topic` is denormalised from the material title at copy time. The bank
 *    outlives the material — deleting a study guide should not erase the
 *    questions a teacher has been reusing for a term — so the label has to
 *    survive independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            $table->uuid('material_id')->nullable()->after('subject_id');
            $table->uuid('source_question_id')->nullable()->after('material_id');
            $table->string('topic')->nullable()->after('source_question_id');

            $table->foreign('material_id')->references('id')->on('materials')->nullOnDelete();
            $table->foreign('source_question_id')->references('id')->on('questions')->nullOnDelete();

            // One bank row per generated question, so approval is idempotent.
            $table->unique('source_question_id');

            // The teacher's view is always "this subject, in this school".
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('question_bank', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropForeign(['source_question_id']);
            $table->dropUnique(['source_question_id']);
            $table->dropIndex(['school_id', 'subject_id']);
            $table->dropColumn(['material_id', 'source_question_id', 'topic']);
        });
    }
};
