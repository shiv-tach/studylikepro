<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tutoring-request feature has been removed, so lessons are booked directly
 * from a teacher's availability. Bookings keeps its own slot, money and status
 * columns; only the link back to the former request disappears.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'tutoring_request_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tutoring_request_id');
            });
        }

        Schema::dropIfExists('request_responses');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('tutoring_requests');
    }

    public function down(): void
    {
        // The feature has been removed; rolling back does not restore the tables.
    }
};
