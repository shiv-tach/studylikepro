<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual settlement: which method the student picked, the bank account they
 * paid into, the uploaded proof and who verified it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('method');
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('bank_accounts')
                ->nullOnDelete();
            $table->string('slip_path')->nullable()->after('bank_account_id');
            $table->string('slip_original_name')->nullable()->after('slip_path');
            $table->string('bank_reference', 100)->nullable()->after('slip_original_name');
            $table->timestamp('submitted_at')->nullable()->after('bank_reference');
            $table->foreignId('verified_by')
                ->nullable()
                ->after('submitted_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['payment_method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropForeign(['verified_by']);
            $table->dropIndex(['payment_method', 'status']);
            $table->dropColumn([
                'payment_method',
                'bank_account_id',
                'slip_path',
                'slip_original_name',
                'bank_reference',
                'submitted_at',
                'verified_by',
            ]);
        });
    }
};
