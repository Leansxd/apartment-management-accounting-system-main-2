<?php

namespace Database\Seeders;

use App\Models\Apartment;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Property;
use App\Models\User;
use App\Services\BankImportService;
use App\Services\FinanceService;
use App\Services\MeterBillService;
use App\Services\PayrollService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(FinanceService $finance): void
    {
        $admin = User::create(['name' => 'Demo Yönetici', 'email' => 'admin@demo.test', 'password' => 'password', 'phone' => '0532 100 20 30']);
        $admin->forceFill(['role' => 'admin', 'is_super_admin' => true])->save();
        $resident = User::create(['name' => 'Ayşe Yılmaz', 'email' => 'sakin@demo.test', 'password' => 'password', 'phone' => '0532 400 50 60']);

        $apt = Apartment::create([
            'name' => 'Demo Rezidans',
            'code' => 'DEMO',
            'city' => 'Antalya',
            'address' => 'Örnek Mah. Deniz Cad. No:1 Konyaaltı / Antalya',
            'owner_id' => $admin->id,
            'late_fee_enabled' => true,
            'late_fee_rate' => 5,
            'late_fee_grace_days' => 0,
        ]);
        $apt->managers()->attach($admin);

        $kasa = $apt->cashboxes()->create(['name' => 'Merkez Kasa', 'type' => 'cash']);
        $banka = $apt->cashboxes()->create(['name' => 'Ziraat Bankası', 'type' => 'bank', 'account_number' => 'TR42 0001 0012 3456 7890 1000 01']);

        $catAidat = $apt->categories()->create(['name' => 'Aidat', 'type' => 'income']);
        $catSuGelir = $apt->categories()->create(['name' => 'Su', 'type' => 'income']);
        $catElektrik = $apt->categories()->create(['name' => 'Elektrik', 'type' => 'expense']);
        $catSuGider = $apt->categories()->create(['name' => 'Su Gideri', 'type' => 'expense']);
        $catTemizlik = $apt->categories()->create(['name' => 'Temizlik', 'type' => 'expense']);
        $catBakim = $apt->categories()->create(['name' => 'Bakım Onarım', 'type' => 'expense']);

        $names = [
            'Ahmet Kaya', 'Mehmet Demir', 'Zeynep Çelik', 'Fatma Şahin', 'Ali Yıldız', 'Elif Aydın',
            'Mustafa Öztürk', 'Emine Arslan', 'Hüseyin Doğan', 'Hatice Kılıç', 'İbrahim Aslan', 'Merve Çetin',
        ];
        $i = 0;
        foreach (['A', 'B'] as $blockName) {
            $block = $apt->blocks()->create(['name' => $blockName]);
            for ($no = 1; $no <= 6; $no++) {
                $unit = $apt->units()->create([
                    'block_id' => $block->id,
                    'unit_no' => (string) $no,
                    'floor' => (string) intdiv($no - 1, 2),
                    'usage_type' => 'Konut',
                    'gross_area' => [95, 110, 125][$no % 3],
                    'land_share' => 0.0833,
                    'status' => 'Dolu',
                ]);
                $member = $apt->members()->create([
                    'unit_id' => $unit->id,
                    'type' => 'owner',
                    'full_name' => $names[$i],
                    'phone' => '0532 '.(210 + $i * 17).' '.(10 + $i * 3).' '.(20 + $i * 4),
                    'email' => $i >= 3 ? 'daire'.($i + 1).'@demo.test' : null,
                    'moved_in_on' => now()->subMonths(14)->toDateString(),
                    'invite_code' => Member::generateInviteCode($apt->code, $unit->load('block')->label()),
                ]);
                if ($blockName === 'A' && $no === 1) {
                    $member->update(['user_id' => $resident->id, 'activated_at' => now()->subMonths(11)]);
                }
                $i++;
            }
        }

        $units = $apt->units()->with('block')->orderBy('id')->get()->values();

        $grpA = $apt->unitGroups()->create(['name' => 'A Blok Bağımsız Bölümler', 'details' => 'A bloktaki tüm daireler']);
        $grpA->units()->sync($units->slice(0, 6)->pluck('id'));
        $grpB = $apt->unitGroups()->create(['name' => 'B Blok Bağımsız Bölümler', 'details' => 'B bloktaki tüm daireler']);
        $grpB->units()->sync($units->slice(6, 6)->pluck('id'));
        $grpUst = $apt->unitGroups()->create(['name' => 'Üst Kat Geniş Daireler', 'details' => 'Üst kat geniş daire grubu']);
        $grpUst->units()->sync($units->filter(fn ($u) => in_array((int) $u->unit_no, [5, 6], true))->pluck('id'));

        $oldMember = $apt->members()->create([
            'unit_id' => $units[2]->id,
            'type' => 'tenant',
            'full_name' => 'Serkan Karaca',
            'phone' => '0533 811 22 33',
            'moved_in_on' => now()->subMonths(20)->toDateString(),
            'moved_out_on' => now()->subMonths(5)->toDateString(),
            'move_out_reason' => 'Sözleşme bitimi nedeniyle taşındı',
        ]);
        $oldMember->delete();

        $apt->duesDefinitions()->create([
            'name' => 'Aylık Aidat',
            'amount' => 1500,
            'category_id' => $catAidat->id,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $vendorTemizlik = $apt->vendors()->create(['name' => 'Akdeniz Temizlik Ltd.', 'tax_office' => 'Kalekapı VD', 'phone' => '0242 311 10 20', 'email' => 'info@akdeniztemizlik.test']);
        $vendorAsansor = $apt->vendors()->create(['name' => 'Toros Asansör Bakım A.Ş.', 'tax_office' => 'Kurumlar VD', 'phone' => '0242 322 40 50', 'email' => 'servis@torosasansor.test']);
        $vendorPeyzaj = $apt->vendors()->create(['name' => 'Antalya Peyzaj & Havuz', 'tax_office' => 'Muratpaşa VD', 'phone' => '0242 333 60 70', 'email' => 'iletisim@antalyapeyzaj.test']);

        foreach ($units as $idx => $u) {
            $meter = $apt->meters()->create([
                'name' => $u->label().' Sıcak Su Sayacı',
                'type' => 'Su',
                'unit_id' => $u->id,
                'serial_no' => 'SU-2025-'.str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT),
                'initial_value' => 100,
                'last_value' => 100,
            ]);
            $val = 100.0;
            for ($rm = 5; $rm >= 0; $rm--) {
                $cons = 8 + (($idx + $rm) % 6) * 1.5;
                $val += $cons;
                $meter->readings()->create([
                    'value' => $val,
                    'consumption' => $cons,
                    'read_on' => now()->startOfMonth()->subMonths($rm)->addDays(4)->toDateString(),
                    'note' => 'Periyodik endeks okuması',
                ]);
            }
            $meter->update(['last_value' => $val]);
        }

        $openDate = CarbonImmutable::now()->startOfMonth()->subMonths(11)->setTime(9, 0);
        $openTx = $finance->generalTransaction($banka, 'income', 12000000, 'Banka açılış bakiyesi', null, $admin);
        CashTransaction::whereKey($openTx->id)->update(['created_at' => $openDate, 'updated_at' => $openDate]);
        LedgerEntry::where('reference', "cash_tx:{$openTx->id}")->update(['created_at' => $openDate]);

        $openKasaTx = $finance->generalTransaction($kasa, 'income', 3500000, 'Kasa açılış bakiyesi', null, $admin);
        CashTransaction::whereKey($openKasaTx->id)->update(['created_at' => $openDate, 'updated_at' => $openDate]);
        LedgerEntry::where('reference', "cash_tx:{$openKasaTx->id}")->update(['created_at' => $openDate]);

        $today = CarbonImmutable::today();

        for ($m = 11; $m >= 0; $m--) {
            $monthStart = CarbonImmutable::now()->startOfMonth()->subMonths($m);
            $period = $monthStart->format('Y-m');
            $maxDay = $m === 0 ? max(0, (int) $monthStart->diffInDays($today)) : min(27, $monthStart->daysInMonth - 1);

            $prevDebtId = (int) Debt::max('id');
            $finance->generateDues($apt, $period, $admin);

            $newDebts = Debt::where('id', '>', $prevDebtId)->orderBy('id')->get();
            foreach ($newDebts as $dIdx => $debt) {
                $dayOffset = $m === 1
                    ? min($maxDay, 2 + ($dIdx * 2))
                    : ($m === 0 ? min($maxDay, intdiv($dIdx * max(1, $maxDay), 12)) : min($maxDay, 1 + ($dIdx % 5)));
                $ts = $monthStart->addDays($dayOffset)->setTime(10, 0);
                Debt::whereKey($debt->id)->update(['created_at' => $ts, 'updated_at' => $ts]);
                LedgerEntry::where('reference', "debt:{$debt->id}")->update(['created_at' => $ts]);
            }

            if ($m === 4) {
                $mbPrevDebt = (int) Debt::max('id');
                app(MeterBillService::class)->distribute($apt, [
                    'meter_type' => 'Su',
                    'method' => 'equal',
                    'period_from' => $monthStart->toDateString(),
                    'period_to' => $monthStart->endOfMonth()->toDateString(),
                    'total_amount' => 3600,
                    'due_date' => $monthStart->addDays(14)->toDateString(),
                    'description' => $period.' Ortak Sıcak Su Paylaşımı',
                ], $admin);
                $mbTs = $monthStart->addDays(5)->setTime(11, 0);
                foreach (Debt::where('id', '>', $mbPrevDebt)->get() as $mbDebt) {
                    Debt::whereKey($mbDebt->id)->update(['created_at' => $mbTs, 'updated_at' => $mbTs]);
                    LedgerEntry::where('reference', "debt:{$mbDebt->id}")->update(['created_at' => $mbTs]);
                }
            }

            foreach ($units as $uIdx => $unit) {
                $shouldPay = match (true) {
                    $m >= 4 => true,
                    $m === 3 || $m === 2 => $uIdx < 10,
                    $m === 1 => $uIdx < 9,
                    default => $uIdx < 8,
                };
                if (! $shouldPay) {
                    continue;
                }

                $payCents = match (true) {
                    $m === 4 => 180000,
                    $m === 0 && $uIdx === 0 => 250000,
                    default => 150000,
                };

                $payDay = $m === 1
                    ? min($maxDay, 6 + ($uIdx * 2))
                    : ($m === 0 ? min($maxDay, intdiv(($uIdx + 1) * max(1, $maxDay), 9)) : min($maxDay, 8 + $uIdx));
                $payTs = $monthStart->addDays($payDay)->setTime(14, ($uIdx * 4) % 60);

                $prevPaymentId = (int) DebtPayment::max('id');
                $targetBox = ($uIdx + $m) % 2 === 0 ? $banka : $kasa;
                $subType = ($uIdx + $m) % 3 === 0 ? 'pos' : 'collection';
                $tx = $finance->collect($unit, $targetBox, $payCents, "{$period} aidat tahsilatı", $admin, null, $subType);

                CashTransaction::whereKey($tx->id)->update(['created_at' => $payTs, 'updated_at' => $payTs]);
                DebtPayment::where('id', '>', $prevPaymentId)->update(['created_at' => $payTs, 'updated_at' => $payTs]);
                LedgerEntry::where('reference', "cash_tx:{$tx->id}")->update(['created_at' => $payTs]);
            }

            $monthlyExpenses = [
                [
                    'vendor_id' => $vendorTemizlik->id,
                    'category_id' => $catTemizlik->id,
                    'description' => $period.' Aylık blok ve ortak alan temizlik hizmeti',
                    'amount' => 5500,
                    'vat_rate' => 20,
                    'total_amount' => 6600,
                    'invoice_no' => 'TMZ-'.$monthStart->format('Ym').'-01',
                    'day' => min($maxDay, 12),
                    'box' => $banka,
                ],
                [
                    'vendor_id' => null,
                    'category_id' => $catElektrik->id,
                    'description' => $period.' Ortak alan ve asansör elektrik faturası',
                    'amount' => 1800 + (($m % 4) * 200),
                    'vat_rate' => 20,
                    'total_amount' => (int) round((1800 + (($m % 4) * 200)) * 1.2),
                    'invoice_no' => 'ELK-'.$monthStart->format('Ym').'-02',
                    'day' => min($maxDay, 16),
                    'box' => $kasa,
                ],
                [
                    'vendor_id' => null,
                    'category_id' => $catSuGider->id,
                    'description' => $period.' Bahçe sulama ve ortak su faturası',
                    'amount' => 900 + (($m % 3) * 150),
                    'vat_rate' => 10,
                    'total_amount' => (int) round((900 + (($m % 3) * 150)) * 1.1),
                    'invoice_no' => 'SU-'.$monthStart->format('Ym').'-03',
                    'day' => min($maxDay, 19),
                    'box' => $kasa,
                ],
            ];

            if ($m % 2 === 0) {
                $monthlyExpenses[] = [
                    'vendor_id' => $vendorAsansor->id,
                    'category_id' => $catBakim->id,
                    'description' => $period.' A ve B blok periyodik asansör bakımı',
                    'amount' => 2500,
                    'vat_rate' => 20,
                    'total_amount' => 3000,
                    'invoice_no' => 'ASN-'.$monthStart->format('Ym').'-04',
                    'day' => min($maxDay, 22),
                    'box' => $banka,
                ];
            }

            foreach ($monthlyExpenses as $eIdx => $expData) {
                $expDate = $monthStart->addDays($expData['day']);
                $expTs = $expDate->setTime(15, $eIdx * 10);
                $prevTxId = (int) CashTransaction::max('id');

                $expense = $apt->expenses()->create([
                    'vendor_id' => $expData['vendor_id'],
                    'category_id' => $expData['category_id'],
                    'description' => $expData['description'],
                    'amount' => $expData['amount'],
                    'vat_rate' => $expData['vat_rate'],
                    'total_amount' => $expData['total_amount'],
                    'invoice_no' => $expData['invoice_no'],
                    'invoice_date' => $expDate->toDateString(),
                    'period' => $period,
                ]);
                $finance->approveExpense($expense, $expData['box'], $admin);

                Expense::whereKey($expense->id)->update(['created_at' => $expTs, 'updated_at' => $expTs]);
                foreach (CashTransaction::where('id', '>', $prevTxId)->get() as $eTx) {
                    CashTransaction::whereKey($eTx->id)->update(['created_at' => $expTs, 'updated_at' => $expTs]);
                    LedgerEntry::where('reference', "cash_tx:{$eTx->id}")->update(['created_at' => $expTs]);
                }
            }
        }

        $apt->expenses()->create([
            'vendor_id' => $vendorAsansor->id,
            'category_id' => $catBakim->id,
            'description' => 'Asansör kabin içi acil durum kartı değişimi',
            'amount' => 2500,
            'vat_rate' => 20,
            'total_amount' => 3000,
            'invoice_no' => 'ASN-BEK-01',
            'invoice_date' => today()->toDateString(),
        ]);
        $apt->expenses()->create([
            'vendor_id' => $vendorPeyzaj->id,
            'category_id' => $catBakim->id,
            'description' => 'Mevsimlik bahçe gübreleme ve palmiye budama',
            'amount' => 3500,
            'vat_rate' => 20,
            'total_amount' => 4200,
            'invoice_no' => 'PYZ-BEK-02',
            'invoice_date' => today()->toDateString(),
        ]);

        $guvenlik = $apt->staff()->create([
            'name' => 'Kemal Bekçi',
            'role' => 'Güvenlik',
            'phone' => '0555 111 22 33',
            'email' => 'kemal@demo.test',
            'salary' => 26500,
            'start_date' => now()->subMonths(18)->toDateString(),
            'status' => 'Aktif',
        ]);
        $apt->staff()->create([
            'name' => 'Murat Bahçıvan',
            'role' => 'Bahçıvan',
            'phone' => '0555 444 55 66',
            'email' => 'murat@demo.test',
            'salary' => 24000,
            'start_date' => now()->subMonths(10)->toDateString(),
            'status' => 'İzinde',
        ]);
        app(PayrollService::class)->generate($apt, now()->format('Y-m'), $admin);

        app(BankImportService::class)->import($apt, $banka, [
            ['date' => today()->subDays(3)->toDateString(), 'description' => 'A-1 Ahmet Kaya Aidat Ödemesi', 'amount' => 150000, 'reference' => 'BNK-9001'],
            ['date' => today()->subDays(2)->toDateString(), 'description' => 'B-4 Hatice Kılıç Aidat EFT', 'amount' => 150000, 'reference' => 'BNK-9002'],
            ['date' => today()->subDay()->toDateString(), 'description' => 'Tanımsız Havale Ödemesi', 'amount' => 150000, 'reference' => 'BNK-9003'],
        ]);

        $year = (int) now()->year;
        $budgetPlans = [
            $catAidat->id => 216000,
            $catSuGelir->id => 18000,
            $catTemizlik->id => 108000,
            $catElektrik->id => 32000,
            $catSuGider->id => 16000,
            $catBakim->id => 42000,
        ];
        foreach ($budgetPlans as $catId => $planned) {
            $apt->budgetItems()->create(['year' => $year, 'category_id' => $catId, 'amount' => $planned]);
        }

        $apt->assets()->create([
            'name' => 'Jeneratör 60 kVA',
            'category' => 'Jeneratör',
            'purchase_date' => now()->subYears(2)->toDateString(),
            'cost' => 180000,
            'useful_life_years' => 10,
            'salvage_value' => 18000,
            'location' => 'Otopark kat -1',
        ]);
        $apt->assets()->create([
            'name' => 'Güvenlik kamera sistemi (16 Kanal IP)',
            'category' => 'Güvenlik Sistemi',
            'purchase_date' => now()->subMonths(8)->toDateString(),
            'cost' => 45000,
            'useful_life_years' => 5,
            'vendor_id' => $vendorAsansor->id,
            'location' => 'Güvenlik kulübesi',
        ]);
        $apt->assets()->create([
            'name' => 'Hidrofor ve Su Pompa Grubu',
            'category' => 'Mekanik Tesisat',
            'purchase_date' => now()->subMonths(11)->toDateString(),
            'cost' => 64000,
            'useful_life_years' => 8,
            'salvage_value' => 4000,
            'location' => 'Teknik oda',
        ]);

        $apt->inventoryItems()->create(['name' => 'Endüstriyel çöp poşeti (rulo)', 'category' => 'Temizlik', 'quantity' => 3, 'unit' => 'rulo', 'min_stock' => 5, 'location' => 'Depo A']);
        $apt->inventoryItems()->create(['name' => 'LED koridor ampulü 12W', 'category' => 'Elektrik', 'quantity' => 4, 'unit' => 'adet', 'min_stock' => 6, 'location' => 'Teknik Depo']);
        $apt->inventoryItems()->create(['name' => 'Yüzey dezenfektanı 5L', 'category' => 'Temizlik', 'quantity' => 12, 'unit' => 'bidon', 'min_stock' => 4, 'location' => 'Depo A']);
        $apt->inventoryItems()->create(['name' => 'Çim biçme misinası', 'category' => 'Bahçe', 'quantity' => 8, 'unit' => 'paket', 'min_stock' => 3, 'location' => 'Bahçe Deposu']);

        $apt->tasks()->create(['title' => 'Bahçe aydınlatma sensörlerini kontrol et', 'staff_id' => $guvenlik->id, 'priority' => 'high', 'category' => 'Elektrik', 'due_date' => today()->addDays(2)]);
        $apt->tasks()->create(['title' => 'B Blok yangın tüpü basınç kontrolü', 'staff_id' => $guvenlik->id, 'priority' => 'medium', 'category' => 'Güvenlik', 'due_date' => today()->addDays(5)]);
        $apt->tasks()->create(['title' => 'Otopark bariyer kumanda alıcısı bakımı', 'staff_id' => $guvenlik->id, 'priority' => 'low', 'category' => 'Bakım', 'status' => 'completed', 'completed_at' => now()->subDays(4), 'due_date' => today()->subDays(5)]);

        $apt->tickets()->create(['subject' => 'Asansör 3. katta ses yapıyor', 'description' => 'B blok asansörü dururken fren sesi geliyor.', 'category' => 'Teknik', 'unit_no' => 'B-3', 'priority' => 'high', 'status' => 'open']);
        $apt->tickets()->create(['subject' => 'Otopark aydınlatma lambası yanmıyor', 'description' => 'A blok girişindeki 2. koridor lambası arızalı.', 'category' => 'Elektrik', 'unit_no' => 'A-4', 'priority' => 'medium', 'status' => 'in_progress']);
        $apt->tickets()->create(['subject' => 'Bahçe sulama saati ayarı', 'description' => 'Sabah erken saatte yürüyüş yoluna su geliyor.', 'category' => 'Bahçe', 'unit_no' => 'A-2', 'priority' => 'low', 'status' => 'resolved']);

        $apt->securityEntries()->create(['kind' => 'cargo', 'unit_no' => 'A-1', 'full_name' => 'Ayşe Yılmaz', 'company' => 'Yurtiçi Kargo', 'tracking_no' => 'YK-884120', 'status' => 'beklemede', 'note' => 'Güvenlik kulübesinde teslim bekliyor']);
        $apt->securityEntries()->create(['kind' => 'cargo', 'unit_no' => 'B-2', 'full_name' => 'Emine Arslan', 'company' => 'Aras Kargo', 'tracking_no' => 'AR-552910', 'status' => 'teslim-edildi', 'closed_at' => now()->subHours(5)]);
        $apt->securityEntries()->create(['kind' => 'parking', 'unit_no' => 'A-3', 'full_name' => 'Misafir Araç - Kemal Bey', 'plate' => '07 ANT 2026', 'status' => 'aktif', 'note' => 'Misafir otoparkı #4']);
        $apt->securityEntries()->create(['kind' => 'visitor', 'unit_no' => 'B-1', 'full_name' => 'Servis Teknisyeni', 'company' => 'Toros Asansör', 'status' => 'aktif', 'note' => 'Makine dairesi kontrolü']);

        $tenis = $apt->commonAreas()->create(['name' => 'Tenis kortu', 'hourly_fee' => 150, 'open_time' => '08:00', 'close_time' => '22:00', 'capacity' => 4]);
        $salon = $apt->commonAreas()->create(['name' => 'Çok amaçlı salon', 'hourly_fee' => 400, 'description' => 'Doğum günü, toplantı ve etkinlik alanı', 'capacity' => 60]);
        app(ReservationService::class)->book($tenis, $units[0], now()->addDays(2)->toDateString(), '18:00', '20:00', $admin, null, true);
        app(ReservationService::class)->book($salon, $units[3], now()->addDays(5)->toDateString(), '14:00', '17:00', $admin, 'Doğum günü organizasyonu', false);

        $meeting = $apt->meetings()->create([
            'title' => now()->year.' Olağan Genel Kurul Toplantısı',
            'meets_at' => now()->addWeeks(3)->setTime(19, 0),
            'location' => 'Sosyal tesis toplantı salonu',
            'agenda' => "1. Açılış ve divan seçimi\n2. 12 aylık gelir-gider ve denetim raporu\n3. Yeni dönem işletme projesi ve bütçe onayı",
            'status' => 'planned',
        ]);
        foreach ($units->take(8) as $u) {
            $meeting->attendances()->create(['unit_id' => $u->id]);
        }

        $apt->decisions()->create([
            'decision_no' => $year.'/001',
            'decided_on' => now()->subMonths(10)->toDateString(),
            'subject' => 'Yıllık işletme bütçesi ve aidat tutarının belirlenmesi',
            'content' => 'Bağımsız bölüm başına aylık aidatın 1.500 TL olarak uygulanmasına oy birliğiyle karar verildi.',
            'attendees' => 'Ahmet Kaya, Mehmet Demir, Zeynep Çelik',
        ]);
        $apt->decisions()->create([
            'decision_no' => $year.'/002',
            'decided_on' => now()->subMonths(6)->toDateString(),
            'subject' => 'Güvenlik kamera sisteminin yenilenmesi',
            'content' => 'Otopark ve bina girişlerine 16 kanallı IP kamera sistemi kurulmasına karar verildi.',
            'attendees' => 'Ahmet Kaya, Mehmet Demir, Ali Yıldız',
        ]);
        $apt->decisions()->create([
            'decision_no' => $year.'/003',
            'decided_on' => now()->subMonths(2)->toDateString(),
            'subject' => 'Asansör periyodik bakım sözleşmesinin uzatılması',
            'content' => 'Toros Asansör Bakım A.Ş. ile mevcut bakım sözleşmesinin 1 yıl süreyle yenilenmesine karar verildi.',
            'attendees' => 'Ahmet Kaya, Zeynep Çelik, Mustafa Öztürk',
        ]);

        $apt->announcements()->create(['title' => 'Cumartesi günü planlı su deposu temizliği', 'content' => 'Cumartesi 10:00-14:00 saatleri arasında hidrofor ve su deposu dezenfeksiyonu yapılacaktır.', 'priority' => 'high', 'created_by' => $admin->id]);
        $apt->announcements()->create(['title' => 'Otopark plaka tanıma sistemi güncellendi', 'content' => 'Yeni araç plakalarınızı yönetim ofisine veya sakin portalı üzerinden bildirmeniz rica olunur.', 'priority' => 'normal', 'created_by' => $admin->id]);

        $poll = $apt->polls()->create(['question' => 'Bahçe alanına yeni çocuk oyun parkı yapılsın mı?', 'due_date' => today()->addWeeks(2)]);
        $optYes = $poll->options()->create(['text' => 'Evet, bu dönem yapılsın']);
        $optNo = $poll->options()->create(['text' => 'Hayır, gelecek yıl değerlendirilsin']);
        $poll->votes()->create(['poll_option_id' => $optYes->id, 'unit_id' => $units[0]->id, 'user_id' => $resident->id]);
        $poll->votes()->create(['poll_option_id' => $optYes->id, 'unit_id' => $units[1]->id]);
        $poll->votes()->create(['poll_option_id' => $optNo->id, 'unit_id' => $units[2]->id]);

        $apt->siteNotifications()->create(['unit_id' => null, 'title' => 'Yeni dönem bütçe raporu yayınlandı', 'body' => 'Son 12 aylık gelir-gider gerçekleşme tablolarını sakin portalından inceleyebilirsiniz.', 'created_by' => $admin->id]);
        $apt->siteNotifications()->create(['unit_id' => $units[0]->id, 'title' => 'Kargonuz güvenliğe teslim edildi', 'body' => 'Yurtiçi Kargo gönderiniz güvenlik noktasından teslim alınabilir.', 'created_by' => $admin->id]);

        $apt->documents()->create(['title' => 'Asansör yıllık bakım sözleşmesi', 'category' => 'Sözleşme', 'file_path' => 'documents/demo.txt', 'original_name' => 'sozlesme.txt', 'size' => 1024, 'expires_on' => now()->addDays(20)]);
        $apt->documents()->create(['title' => 'Site yönetim planı ve iç yönetmelik', 'category' => 'Yönetmelik', 'file_path' => 'documents/yonetmelik.pdf', 'original_name' => 'yonetmelik.pdf', 'size' => 4096, 'expires_on' => now()->addMonths(8)]);

        Property::create([
            'title' => 'Demo Rezidans A Blok Deniz Manzaralı 3+1 Daire',
            'price' => 8750000,
            'location' => 'Konyaaltı / Antalya',
            'category' => 'Apartment',
            'status' => 'active',
            'bedrooms' => 3,
            'bathrooms' => 2,
            'balconies' => 2,
            'area' => 135,
            'is_furnished' => true,
            'is_credit_eligible' => true,
            'features' => ['Havuz', 'Otopark', 'Asansör', 'Güvenlik', 'Deniz Manzarası'],
            'facades' => ['Güney', 'Batı'],
            'created_by' => $admin->id,
        ]);
        Property::create([
            'title' => 'Demo Rezidans B Blok Bahçe Katı 2+1 Daire',
            'price' => 6400000,
            'location' => 'Konyaaltı / Antalya',
            'category' => 'Apartment',
            'status' => 'active',
            'bedrooms' => 2,
            'bathrooms' => 1,
            'balconies' => 1,
            'area' => 105,
            'is_furnished' => false,
            'is_credit_eligible' => true,
            'features' => ['Havuz', 'Otopark', 'Bahçe', 'Güvenlik'],
            'facades' => ['Doğu', 'Güney'],
            'created_by' => $admin->id,
        ]);

        $overdueDebt = $apt->debts()->where('status', 'pending')->orderBy('due_date')->first();
        $overdueDebt && $finance->setLegal($overdueDebt, true, 'Örnek icra dosyası 2026/145', $admin);
    }
}
