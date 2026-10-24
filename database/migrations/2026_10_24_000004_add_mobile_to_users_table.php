<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Canonical local form, e.g. "0769828874" (see App\Support\PhoneNumber).
            // Nullable so accounts created before this migration stay valid; a
            // unique index still allows any number of NULL rows.
            $table->string('mobile', 15)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropColumn('mobile');
        });
    }
};
