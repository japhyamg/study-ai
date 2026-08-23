<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — AI/Learning.
 *
 * Turns the flat material record into a reviewable pipeline and gives topics a
 * real graph.
 *
 * What changes and why:
 *
 *  materials    The old pair of columns (status: draft|processing|ready|failed
 *               plus review_status: pending|approved|rejected) could not
 *               express the states that actually occur — "AI finished, teacher
 *               hasn't submitted it", or "admin asked for changes". They are
 *               replaced by one `workflow_state` column covering the whole
 *               path. Also adds the file-upload columns (there was no upload
 *               path at all) and `review_notes`, which the reject action has
 *               been writing to a column that does not exist.
 *
 *  study_guides Gains title + `sections` JSON. A guide was a single blob, so
 *               the UI could not render or link to a section.
 *
 *  topics       Was a per-user scratch table with no school, subject or
 *               material pointer. Rescoped to the school and given
 *               `topic_links` so prerequisite/related/follow-up edges can be
 *               stored and walked in both directions.
 *
 *  submission_notes
 *               The audit trail for the review conversation.
 *
 * Existing rows are mapped onto the new workflow states rather than dropped.
 */
return new class extends Migration
{
    /** Old (status, review_status, published) → new workflow_state. */
    private const STATE_MAP = [
        'draft|pending' => 'draft',
        'processing|pending' => 'ai_processing',
        'ready|pending' => 'ai_completed',
        'failed|pending' => 'ai_failed',
        'ready|approved' => 'approved',
        'ready|rejected' => 'rejected',
        'draft|approved' => 'approved',
        'draft|rejected' => 'rejected',
    ];

    public function up(): void
    {
        $this->upgradeMaterials();
        $this->upgradeStudyGuides();
        $this->upgradeTopics();
        $this->createTopicLinks();
        $this->createSubmissionNotes();
    }

    // ───────────────────────── materials ─────────────────────────

    private function upgradeMaterials(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // One column for the whole lifecycle:
            //   draft → ai_processing → ai_completed | ai_failed
            //         → submitted → under_review
            //         → approved | changes_requested | rejected
            //         → published
            $table->string('workflow_state', 32)->default('draft')->after('status');

            // Uploads. Previously the only way in was pasting text.
            $table->string('file_path')->nullable()->after('storage_url');
            $table->string('file_name')->nullable()->after('file_path');
            $table->string('file_type', 20)->nullable()->after('file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_type');

            // Teacher's generation config, captured at upload time so a re-run
            // reproduces the same request.
            $table->json('generation_config')->nullable()->after('transcript');

            // The reject action already writes this; the column was missing, so
            // every rejection reason was silently discarded.
            $table->text('review_notes')->nullable()->after('review_status');

            $table->timestamp('ai_processed_at')->nullable()->after('published_at');
            $table->timestamp('submitted_at')->nullable()->after('ai_processed_at');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->uuid('reviewed_by')->nullable()->after('reviewed_at');

            $table->index(['school_id', 'workflow_state']);
            $table->index(['created_by', 'workflow_state']);
        });

        // SQLite cannot add a FK to an existing table; skip it there rather
        // than failing the migration outright.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('materials', function (Blueprint $table) {
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->backfillWorkflowStates();
    }

    private function backfillWorkflowStates(): void
    {
        foreach (self::STATE_MAP as $pair => $state) {
            [$status, $review] = explode('|', $pair);

            DB::table('materials')
                ->where('status', $status)
                ->where('review_status', $review)
                ->update(['workflow_state' => $state]);
        }

        // Anything already visible to students is published, whatever the
        // legacy column pair happened to say.
        DB::table('materials')
            ->where('published', true)
            ->update(['workflow_state' => 'published']);

        DB::table('materials')
            ->whereNotNull('published_at')
            ->update(['ai_processed_at' => DB::raw('published_at')]);
    }

    // ───────────────────────── study guides ─────────────────────────

    private function upgradeStudyGuides(): void
    {
        Schema::table('study_guides', function (Blueprint $table) {
            $table->string('title')->nullable()->after('material_id');
            $table->json('sections')->nullable()->after('content');
            $table->json('key_terms')->nullable()->after('sections');
            $table->text('summary')->nullable()->after('key_terms');
        });

        // The old `content` column holds a JSON blob shaped
        // {title, summary, sections, keyTerms}. Lift those into real columns.
        foreach (DB::table('study_guides')->get() as $guide) {
            $decoded = json_decode($guide->content ?? '', true);

            if (! is_array($decoded)) {
                continue;
            }

            DB::table('study_guides')->where('id', $guide->id)->update([
                'title' => $decoded['title'] ?? null,
                'summary' => $decoded['summary'] ?? null,
                'sections' => isset($decoded['sections']) ? json_encode($decoded['sections']) : null,
                'key_terms' => isset($decoded['keyTerms']) ? json_encode($decoded['keyTerms']) : null,
            ]);
        }
    }

    // ───────────────────────── topics ─────────────────────────

    private function upgradeTopics(): void
    {
        // A topic derived from a material belongs to the school, not to a
        // person, so user_id has to become optional. SQLite cannot alter a
        // column in place, so it keeps the original NOT NULL there and the
        // service always supplies a creator.
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE topics MODIFY user_id CHAR(36) NULL'),
            'pgsql' => DB::statement('ALTER TABLE topics ALTER COLUMN user_id DROP NOT NULL'),
            // SQLite cannot alter a column in place; the service always
            // supplies a creator, so the constraint is never hit there.
            default => null,
        };

        Schema::table('topics', function (Blueprint $table) {
            $table->uuid('school_id')->nullable()->after('id');
            $table->uuid('subject_id')->nullable()->after('school_id');
            $table->uuid('material_id')->nullable()->after('subject_id');
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');

            $table->index(['school_id', 'subject_id']);
            $table->index('material_id');
        });

        // Existing topics are personal scratch notes with a user but no school.
        // Adopt the user's school so they stay visible under tenant scoping.
        if (Schema::hasTable('users')) {
            DB::statement('
                UPDATE topics
                   SET school_id = (SELECT school_id FROM users WHERE users.id = topics.user_id)
                 WHERE school_id IS NULL
            ');
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('topics', function (Blueprint $table) {
                $table->foreign('school_id')->references('id')->on('schools')->cascadeOnDelete();
                $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
                $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
            });
        }
    }

    private function createTopicLinks(): void
    {
        Schema::create('topic_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('topic_id');
            $table->uuid('linked_topic_id');

            // prerequisite | related | follow_up
            $table->string('relationship_type', 20)->default('related');

            // 0.00–1.00. Manual links are stored at 1.00 so a teacher's edge
            // always outranks an AI guess when sorting.
            $table->decimal('confidence_score', 3, 2)->default(0.50);
            $table->boolean('is_manual')->default(false);

            $table->timestamps();

            $table->unique(['topic_id', 'linked_topic_id', 'relationship_type'], 'topic_links_edge_unique');
            $table->index('linked_topic_id');

            $table->foreign('topic_id')->references('id')->on('topics')->cascadeOnDelete();
            $table->foreign('linked_topic_id')->references('id')->on('topics')->cascadeOnDelete();
        });
    }

    // ───────────────────────── review trail ─────────────────────────

    private function createSubmissionNotes(): void
    {
        Schema::create('submission_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id');
            $table->uuid('user_id')->nullable();

            // submission | change_request | admin_note | approval | rejection
            $table->string('note_type', 20);
            $table->text('content');

            $table->timestamps();

            $table->index(['material_id', 'created_at']);

            $table->foreign('material_id')->references('id')->on('materials')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_notes');
        Schema::dropIfExists('topic_links');

        Schema::table('topics', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['school_id']);
                $table->dropForeign(['subject_id']);
                $table->dropForeign(['material_id']);
            }
            $table->dropColumn(['school_id', 'subject_id', 'material_id', 'slug', 'description']);
        });

        Schema::table('study_guides', function (Blueprint $table) {
            $table->dropColumn(['title', 'sections', 'key_terms', 'summary']);
        });

        Schema::table('materials', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['reviewed_by']);
            }
            $table->dropColumn([
                'workflow_state', 'file_path', 'file_name', 'file_type', 'file_size',
                'generation_config', 'review_notes', 'ai_processed_at', 'submitted_at',
                'reviewed_at', 'reviewed_by',
            ]);
        });
    }
};
