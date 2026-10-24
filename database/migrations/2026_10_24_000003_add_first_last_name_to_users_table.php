<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // `name` stays as the canonical full name so search, sorting,
            // receipts and exports keep working; these are the parts the
            // registration and profile forms actually collect.
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
        });

        // Split the existing single name on its last space so no data is lost:
        // "Asha Perera" → first "Asha", last "Perera"; "Asha" → first only.
        DB::table('users')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $name = trim((string) $user->name);

                if ($name === '') {
                    continue;
                }

                if (preg_match('/^(.*)\s(\S+)$/u', $name, $matches) === 1) {
                    [$firstName, $lastName] = [trim($matches[1]), $matches[2]];
                } else {
                    [$firstName, $lastName] = [$name, null];
                }

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
