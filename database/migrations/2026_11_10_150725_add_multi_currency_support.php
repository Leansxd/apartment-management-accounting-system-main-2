<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Çok dövizli tahsilat. Raporlama para birimi TRY kalır: cashboxes.balance ve cash_transactions.amount
// her zaman TRY karşılığıdır. Döviz kasasının gerçek mevcudu currency_balance kolonundadır; her hareket
// ödeme anındaki orijinal tutarı ve kuru saklar. Mevcut kayıtlar TRY ve kur 1 olarak kalır.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashboxes', function (Blueprint $table) {
            $table->decimal('currency_balance', 14, 2)->default(0)->after('balance');
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->string('currency', 3)->default('TRY')->after('amount');
            $table->decimal('original_amount', 14, 2)->nullable()->after('currency');
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('original_amount');
            $table->string('payment_group', 36)->nullable()->after('exchange_rate')->index();
        });

        DB::table('cash_transactions')->update(['original_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_group']);
            $table->dropColumn(['currency', 'original_amount', 'exchange_rate', 'payment_group']);
        });

        Schema::table('cashboxes', fn (Blueprint $table) => $table->dropColumn('currency_balance'));
    }
};
