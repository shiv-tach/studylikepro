<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The in-browser marking studio keeps three pieces of extra state:
     *
     * - rubric_snapshot: the criteria as they stood when marking started, so a
     *   later offering edit can never orphan a saved score.
     * - rubric_scores: the per-criterion score the teacher awarded.
     * - annotations: the vector markup (strokes, pins, stamps, corrections)
     *   so the studio resumes after a reload. The delivered artifact is still
     *   the flattened PDF in marked_files.
     */
    public function up(): void
    {
        Schema::table('paper_marking_orders', function (Blueprint $table) {
            $table->json('rubric_snapshot')->nullable()->after('marks');
            $table->json('rubric_scores')->nullable()->after('rubric_snapshot');
            $table->json('annotations')->nullable()->after('rubric_scores');
            $table->timestamp('draft_saved_at')->nullable()->after('annotations');
        });
    }

    public function down(): void
    {
        Schema::table('paper_marking_orders', function (Blueprint $table) {
            $table->dropColumn(['rubric_snapshot', 'rubric_scores', 'annotations', 'draft_saved_at']);
        });
    }
};
