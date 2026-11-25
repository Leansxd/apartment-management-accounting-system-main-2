<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borcun sorumlusu (malik / kiracı / daire geneli), tahsilatı ödeyen sakin ve giderlerin kısmi ödemesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->string('responsible', 10)->nullable()->after('kind');
        });
        Schema::table('dues_definitions', function (Blueprint $table) {
            $table->string('responsible', 10)->nullable()->after('calculation');
        });
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('payer_member_id')->nullable()->after('unit_id')->constrained('members')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('paid_amount', 14, 2)->default(0)->after('total_amount');
        });

        // Önceden onaylanan giderler tamamen ödenmiştir
        DB::table('expenses')->where('status', 'approved')->update(['paid_amount' => DB::raw('total_amount')]);
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payer_member_id');
        });
        Schema::table('dues_definitions', function (Blueprint $table) {
            $table->dropColumn('responsible');
        });
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('responsible');
        });
    }
};
