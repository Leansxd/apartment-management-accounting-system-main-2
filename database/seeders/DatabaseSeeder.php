<?php

namespace Database\Seeders;

use App\Models\Apartment;
use App\Models\Member;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;

/**
 * Demo verisi: bir süper admin, bir mobil sakin hesabı ve 2 bloklu örnek site
 * (daireler, sakinler, aidat tanımı, iki dönem tahakkuk, tahsilatlar, gider, duyuru, görev, talep).
 *   php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(FinanceService $finance): void
    {
        $admin = User::create(['name' => 'Demo Yönetici', 'email' => 'admin@demo.test', 'password' => 'password']);
        $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();
        $resident = User::create(['name' => 'Ayşe Yılmaz', 'email' => 'sakin@demo.test', 'password' => 'password']);

        $apt = Apartment::create(['name' => 'Demo Rezidans', 'code' => 'DEMO', 'city' => 'Antalya',
            'address' => 'Örnek Mah. Deniz Cad. No:1', 'owner_id' => $admin->id]);
        $apt->managers()->attach($admin);

        $kasa = $apt->cashboxes()->create(['name' => 'Merkez Kasa', 'type' => 'cash']);
        $banka = $apt->cashboxes()->create(['name' => 'Ziraat Bankası', 'type' => 'bank', 'account_number' => 'TR00 0000 0000 0000']);
        $aidat = $apt->categories()->create(['name' => 'Aidat', 'type' => 'income']);
        foreach (['Elektrik', 'Su', 'Temizlik', 'Bakım Onarım'] as $name) {
            $apt->categories()->create(['name' => $name, 'type' => 'expense']);
        }

        $names = ['Ahmet Kaya', 'Mehmet Demir', 'Zeynep Çelik', 'Fatma Şahin', 'Ali Yıldız', 'Elif Aydın',
            'Mustafa Öztürk', 'Emine Arslan', 'Hüseyin Doğan', 'Hatice Kılıç', 'İbrahim Aslan', 'Merve Çetin'];
        $i = 0;
        foreach (['A', 'B'] as $blockName) {
            $block = $apt->blocks()->create(['name' => $blockName]);
            for ($no = 1; $no <= 6; $no++) {
                $unit = $apt->units()->create(['block_id' => $block->id, 'unit_no' => (string) $no, 'floor' => (string) intdiv($no - 1, 2),
                    'gross_area' => [95, 110, 125][$no % 3], 'land_share' => 0.0833]);
                $member = $apt->members()->create([
                    'unit_id' => $unit->id, 'type' => 'owner', 'full_name' => $names[$i++],
                    'phone' => '0532 '.random_int(100, 999).' '.random_int(10, 99).' '.random_int(10, 99),
                    'invite_code' => Member::generateInviteCode($apt->code, $unit->load('block')->label()),
                ]);
                if ($blockName === 'A' && $no === 1) {
                    $member->update(['user_id' => $resident->id, 'activated_at' => now()]);
                }
            }
        }

        $apt->duesDefinitions()->create(['name' => 'Aylık Aidat', 'amount' => 1500, 'category_id' => $aidat->id, 'due_day' => 10]);
        $finance->generalTransaction($banka, 'income', 5000000, 'Açılış bakiyesi', null, $admin);

        foreach ([now()->subMonths(2)->format('Y-m'), now()->subMonth()->format('Y-m')] as $period) {
            $finance->generateDues($apt, $period, $admin);
        }

        // Dairelerin çoğu öder, bazıları gecikir, biri fazla öder (avans)
        foreach ($apt->units()->with('block')->get() as $index => $unit) {
            $amount = match (true) {
                $index % 4 === 3 => 0,          // borçlu
                $index % 5 === 0 => 400000,     // fazla ödeme -> avans
                default => 300000,
            };
            if ($amount) {
                $finance->collect($unit, $index % 2 ? $kasa : $banka, $amount, null, $admin, null, $index % 3 ? 'collection' : 'pos');
            }
        }

        $vendor = $apt->vendors()->create(['name' => 'Akdeniz Temizlik Ltd.', 'phone' => '0242 000 00 00']);
        $expense = $apt->expenses()->create(['vendor_id' => $vendor->id, 'description' => 'Aylık temizlik hizmeti',
            'amount' => 8000, 'vat_rate' => 20, 'total_amount' => 9600, 'invoice_no' => 'F-2026-001', 'invoice_date' => today()]);
        $finance->approveExpense($expense, $banka, $admin);
        $apt->expenses()->create(['description' => 'Asansör bakımı', 'amount' => 2500, 'vat_rate' => 20, 'total_amount' => 3000]);

        $apt->announcements()->create(['title' => 'Su kesintisi', 'content' => 'Cumartesi 10:00-14:00 arası bakım nedeniyle su kesintisi olacaktır.', 'created_by' => $admin->id]);
        $guvenlik = $apt->staff()->create(['name' => 'Kemal Bekçi', 'role' => 'Güvenlik', 'phone' => '0555 000 00 00']);
        $apt->tasks()->create(['title' => 'Bahçe aydınlatmasını kontrol et', 'staff_id' => $guvenlik->id, 'priority' => 'high', 'due_date' => today()->addDays(2)]);
        $apt->tickets()->create(['subject' => 'Asansör ses yapıyor', 'category' => 'Teknik', 'unit_no' => 'B-3', 'priority' => 'high']);
        $poll = $apt->polls()->create(['question' => 'Bahçeye çocuk parkı yapılsın mı?', 'due_date' => today()->addWeeks(2)]);
        $poll->options()->createMany([['text' => 'Evet'], ['text' => 'Hayır']]);
        $apt->inventoryItems()->create(['name' => 'Çöp poşeti (rulo)', 'category' => 'Temizlik', 'quantity' => 3, 'min_stock' => 5]);
    }
}
