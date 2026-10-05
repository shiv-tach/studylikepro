<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // `meeting_url` (from the original schema) holds the participant link;
            // the teacher receives a host link with extra privileges.
            $table->string('meeting_status')->nullable()->after('meeting_provider');
            $table->string('meeting_external_id')->nullable()->after('meeting_status');
            $table->text('host_meeting_url')->nullable()->after('meeting_url');
            $table->text('meeting_error')->nullable()->after('host_meeting_url');
            $table->timestamp('meeting_started_at')->nullable()->after('meeting_error');
            $table->timestamp('meeting_ended_at')->nullable()->after('meeting_started_at');
            $table->string('meeting_recording_url')->nullable()->after('meeting_ended_at');

            $table->index('meeting_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['meeting_status']);
            $table->dropColumn([
                'meeting_status',
                'meeting_external_id',
                'host_meeting_url',
                'meeting_error',
                'meeting_started_at',
                'meeting_ended_at',
                'meeting_recording_url',
            ]);
        });
    }
};
