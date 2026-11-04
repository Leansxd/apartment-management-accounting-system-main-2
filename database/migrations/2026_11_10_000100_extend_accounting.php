<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Muhasebe genişletmeleri: gecikme zammı ayarları, borç türü / taksit / hukuki takip, işlem iptali.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            // KMK m.20: aylık %5 gecikme tazminatı (varsayılan kapalı; yönetici açar)
            $table->boolean('late_fee_enabled')->default(false);
            $table->decimal('late_fee_rate', 5, 2)->default(5);
            $table->unsignedSmallInteger('late_fee_grace_days')->default(0);
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->string('kind')->default('due')->index(); // due | late_fee | installment
            $table->unsignedBigInteger('parent_debt_id')->nullable()->index();
            $table->string('legal_status')->nullable(); // icra
            $table->string('legal_note')->nullable();
            $table->date('legal_at')->nullable();
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', fn (Blueprint $t) => $t->dropColumn(['reversed_at', 'reversed_by', 'reversal_reason', 'reversal_of_id']));
        Schema::table('debts', fn (Blueprint $t) => $t->dropColumn(['kind', 'parent_debt_id', 'legal_status', 'legal_note', 'legal_at']));
        Schema::table('apartments', fn (Blueprint $t) => $t->dropColumn(['late_fee_enabled', 'late_fee_rate', 'late_fee_grace_days']));
    }
};
