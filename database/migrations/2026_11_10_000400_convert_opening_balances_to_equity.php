<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Gelir olarak işlenmiş eski kasa açılış bakiyelerini ve iptallerini açılış hesabına taşır.
return new class extends Migration
{
    private const INCOME_ACCOUNT = 'Gelir Hesabı';

    private const OPENING_ACCOUNT = 'Açılış Bakiyesi Hesabı';

    public function up(): void
    {
        DB::transaction(function (): void {
            $openingIds = DB::table('cash_transactions')->where('sub_type', 'general')->where('type', 'income')
                ->where('description', 'Açılış bakiyesi')->whereNull('category_id')->pluck('id');
            $reversalIds = DB::table('cash_transactions')->whereIn('reversal_of_id', $openingIds)->pluck('id');

            DB::table('cash_transactions')->whereIn('id', $openingIds)->update(['sub_type' => 'opening']);
            DB::table('ledger_entries')->where('account_name', self::INCOME_ACCOUNT)
                ->whereIn('reference', $openingIds->concat($reversalIds)->map(fn (int $id): string => "cash_tx:{$id}"))
                ->update(['account_name' => self::OPENING_ACCOUNT]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            DB::table('cash_transactions')->where('sub_type', 'opening')->update(['sub_type' => 'general']);
            DB::table('ledger_entries')->where('entry_type', 'opening')->update(['entry_type' => 'general']);
            DB::table('ledger_entries')->where('account_name', self::OPENING_ACCOUNT)->update(['account_name' => self::INCOME_ACCOUNT]);
        });
    }
};
