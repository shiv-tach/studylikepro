<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service purchases settle at the purchase level, so payments, refunds and
 * earnings rows must be able to exist without a booking. `booking_id` becomes
 * nullable on all three tables (SQLite rebuilds the column; MySQL changes it
 * in place).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->redefineBookingId('payments', ['service_purchase_id'], false);
        $this->redefineBookingId('refunds', ['payment_id'], false);
        $this->redefineBookingId('teacher_earnings', ['service_purchase_id'], true);
    }

    public function down(): void
    {
        $this->redefineBookingId('payments', ['service_purchase_id'], false, required: true);
        $this->redefineBookingId('refunds', ['payment_id'], false, required: true);
        $this->redefineBookingId('teacher_earnings', ['service_purchase_id'], true, required: true);
    }

    /**
     * Drop the foreign key, the unique index and the column, then re-add the
     * column as nullable (or NOT NULL for rollbacks). The order matters:
     * MySQL refuses to drop an index a foreign key still needs, and SQLite
     * rebuilds the table when the foreign key is dropped.
     *
     * @param  list<string>  $after
     */
    private function redefineBookingId(string $table, array $after, bool $withUnique, bool $required = false): void
    {
        Schema::table($table, function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });

        Schema::table($table, function (Blueprint $table) use ($withUnique) {
            if ($withUnique) {
                $table->dropUnique(['booking_id']);
            }
        });

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn('booking_id');
        });

        Schema::table($table, function (Blueprint $table) use ($after, $withUnique, $required) {
            $column = $table->foreignId('booking_id');

            if (! $required) {
                $column->nullable();
            }

            if ($after !== []) {
                $column->after($after[0]);
            }

            $column->constrained()->cascadeOnDelete();

            if ($withUnique) {
                $table->unique(['booking_id', 'service_purchase_id']);
            }
        });
    }
};
