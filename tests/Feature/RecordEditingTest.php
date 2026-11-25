<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\Debt;
use App\Models\Meter;
use App\Models\MeterBill;
use App\Models\MeterReading;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceException;
use App\Services\FinanceService;
use App\Services\MeterBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordEditingTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-15 12:00');
        $this->finance = app(FinanceService::class);
        $this->manager = $this->admin(true);
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->actingAs($this->manager);
    }

    private function debt(int $amount = 100000, string $due = '2026-04-30'): Debt
    {
        $this->finance->accrue($this->flat, ['description' => 'Aidat', 'amount' => $amount, 'due_date' => $due]);

        return Debt::latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function debtForm(array $overrides = []): array
    {
        return $overrides + ['description' => 'Yeni açıklama', 'due_date' => '2026-05-31', 'amount' => 1000];
    }

    private function meter(float $initial = 100): Meter
    {
        return $this->site->meters()->create(['name' => 'Su', 'type' => 'Su', 'unit_id' => $this->flat->id, 'initial_value' => $initial, 'last_value' => $initial]);
    }

    private function reading(Meter $meter, float $value, string $date = '2026-03-01'): MeterReading
    {
        $this->post(route('site.meters.readings', [$this->site, $meter]), ['value' => $value, 'read_on' => $date]);

        return MeterReading::latest('id')->firstOrFail();
    }

    private function bill(string $method = 'equal'): MeterBill
    {
        return app(MeterBillService::class)->distribute($this->site, [
            'meter_type' => 'Su', 'method' => $method, 'period_from' => '2026-02-01', 'period_to' => '2026-03-10',
            'total_amount' => 100, 'description' => 'Su faturası', 'due_date' => '2026-04-30',
        ], $this->manager)['bill'];
    }

    public function test_borc_tutari_degisince_yevmiye_dengede_kalir_ve_kaydedilir(): void
    {
        $debt = $this->debt();

        $this->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm(['amount' => 1500]))->assertSessionHas('status');

        $debt->refresh();
        $this->assertEquals(1500, (float) $debt->amount);
        $this->assertEquals(1500, (float) $debt->remaining_amount);
        $this->assertSame('Yeni açıklama', $debt->description);
        $this->assertLedgerBalanced($this->site);
        $sums = $this->site->ledgerEntries()->where('reference', "debt:{$debt->id}")->where('account_name', 'Üye/Daire Hesabı')->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $this->assertEquals(1500, (float) $sums->d - (float) $sums->c);
        $this->assertEquals(1000, (float) AuditLog::where('action', 'UPDATE_DEBT')->firstOrFail()->data['changes']['amount']['old']);
    }

    public function test_kategori_degisince_gelir_yeni_kategoriye_gecer(): void
    {
        $debt = $this->debt();
        $category = $this->site->categories()->create(['name' => 'Yakıt', 'type' => 'income']);

        $this->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm(['category_id' => $category->id]));

        $debt->refresh();
        $this->assertSame($category->id, $debt->category_id);
        $this->assertSame('Yakıt', $debt->category_name);
        $this->assertLedgerBalanced($this->site);
        $this->assertEquals(1000, (float) $this->site->ledgerEntries()->where('account_name', 'Gelir Hesabı')->where('account_id', (string) $category->id)->sum('credit'));
    }

    public function test_yalnizca_aciklama_degisince_yevmiye_yazilmaz(): void
    {
        $debt = $this->debt();
        $before = $this->site->ledgerEntries()->count();

        $this->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm());

        $this->assertSame($before, $this->site->ledgerEntries()->count());
    }

    public function test_degisiklik_yoksa_kayit_yazilmaz(): void
    {
        $debt = $this->debt();

        $this->put(route('site.debts.update', [$this->site, $debt]), ['description' => 'Aidat', 'due_date' => '2026-04-30', 'amount' => 1000])->assertSessionHas('status');

        $this->assertSame(0, AuditLog::where('action', 'UPDATE_DEBT')->count());
    }

    public function test_odeme_alinmis_borcta_aciklama_degisir_tutar_degismez(): void
    {
        $debt = $this->debt();
        $this->finance->collect($this->flat, $this->cashbox($this->site), 40000);

        $this->put(route('site.debts.update', [$this->site, $debt]), ['description' => 'Düzeltildi', 'due_date' => '2026-05-31'])->assertSessionHas('status');
        $this->assertSame('Düzeltildi', $debt->fresh()->description);

        $this->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm(['amount' => 1500]))->assertSessionHas('error');
        $this->assertEquals(1000, (float) $debt->fresh()->amount);
        $this->assertEquals(600, (float) $debt->fresh()->remaining_amount);
    }

    public function test_kapanmis_borc_ve_gecikme_zammi_duzenlenemez(): void
    {
        $paid = $this->debt();
        $this->finance->collect($this->flat, $this->cashbox($this->site), 100000);
        $lateFee = $this->debt();
        $lateFee->update(['kind' => 'late_fee']);

        $this->put(route('site.debts.update', [$this->site, $paid]), $this->debtForm())->assertSessionHas('error');
        $this->put(route('site.debts.update', [$this->site, $lateFee]), $this->debtForm())->assertSessionHas('error');

        $this->assertSame('Aidat', $paid->fresh()->description);
        $this->assertSame('Aidat', $lateFee->fresh()->description);
    }

    public function test_gecikme_zammi_yazilmis_borcun_tutari_degismez(): void
    {
        $debt = $this->debt();
        $lateFee = $this->debt();
        $lateFee->update(['kind' => 'late_fee', 'parent_debt_id' => $debt->id]);

        $this->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm(['amount' => 1500]))->assertSessionHas('error');

        $this->assertEquals(1000, (float) $debt->fresh()->amount);
    }

    public function test_kapali_donem_borcu_duzenlenemez_ve_vade_kapali_doneme_alinamaz(): void
    {
        $open = $this->debt();
        $this->travelTo('2026-01-10 12:00');
        $closed = $this->debt(100000, '2026-01-31');
        $this->travelTo('2026-03-15 12:00');
        $this->site->update(['closed_until' => '2026-01-31']);

        $this->put(route('site.debts.update', [$this->site, $closed]), $this->debtForm())->assertSessionHas('error');
        $this->put(route('site.debts.update', [$this->site, $open]), $this->debtForm(['due_date' => '2026-01-15']))->assertSessionHas('error');

        $this->assertSame('Aidat', $closed->fresh()->description);
        $this->assertSame('2026-04-30', $open->fresh()->due_date->toDateString());
    }

    public function test_baska_sitenin_borcu_duzenlenemez_ve_izleyici_yazamaz(): void
    {
        $other = $this->site(1, $this->manager);
        $debt = $this->debt();
        $this->put(route('site.debts.update', [$other, $debt]), $this->debtForm())->assertNotFound();

        $viewer = $this->admin();
        $this->site->managers()->attach($viewer, ['role' => 'viewer']);
        $this->actingAs($viewer)->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm())->assertForbidden();

        $this->assertSame('Aidat', $debt->fresh()->description);
    }

    public function test_bekleyen_gider_duzenlenir_kdv_yeniden_hesaplanir(): void
    {
        $expense = $this->site->expenses()->create(['description' => 'Elektrik', 'amount' => 100, 'vat_rate' => 20, 'total_amount' => 120]);

        $this->put(route('site.expenses.update', [$this->site, $expense]), ['description' => 'Su', 'amount' => 200, 'vat_rate' => 10])->assertSessionHas('status');

        $expense->refresh();
        $this->assertSame('Su', $expense->description);
        $this->assertEquals(220, (float) $expense->total_amount);
        $this->assertEquals(120, (float) AuditLog::where('action', 'UPDATE_EXPENSE')->firstOrFail()->data['changes']['total_amount']['old']);
    }

    public function test_onayli_ve_kapali_donem_gideri_duzenlenemez(): void
    {
        $this->finance->generalTransaction($this->cashbox($this->site), 'income', 100000, 'Bağış');
        $approved = $this->site->expenses()->create(['description' => 'Onaylı', 'amount' => 100, 'total_amount' => 120]);
        $this->finance->approveExpense($approved, $this->cashbox($this->site)->fresh());
        $this->travelTo('2026-01-10 12:00');
        $old = $this->site->expenses()->create(['description' => 'Eski', 'amount' => 100, 'total_amount' => 120]);
        $this->travelTo('2026-03-15 12:00');
        $this->site->update(['closed_until' => '2026-01-31']);
        $form = ['description' => 'Değişti', 'amount' => 200, 'vat_rate' => 20];

        $this->put(route('site.expenses.update', [$this->site, $approved]), $form)->assertSessionHas('error');
        $this->put(route('site.expenses.update', [$this->site, $old]), $form)->assertSessionHas('error');

        $this->assertSame('Onaylı', $approved->fresh()->description);
        $this->assertSame('Eski', $old->fresh()->description);
    }

    public function test_aidat_tanimi_duzenlenince_yazilmis_borc_degismez_sonraki_donem_yeni_tutarla_uretilir(): void
    {
        $definition = $this->site->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);
        $this->finance->generateDues($this->site, '2026-03');

        $this->put(route('site.dues.update', [$this->site, $definition]), [
            'name' => 'Yeni aidat', 'amount' => 700, 'calculation' => 'fixed', 'target_type' => 'unit', 'unit_id' => $this->flat->id, 'due_day' => 15,
        ])->assertSessionHas('status');

        $this->assertEquals(500, (float) Debt::where('period', '2026-03')->where('unit_id', $this->flat->id)->firstOrFail()->amount);
        $this->finance->generateDues($this->site, '2026-04');
        $this->assertSame(1, Debt::where('period', '2026-04')->count());
        $this->assertEquals(700, (float) Debt::where('period', '2026-04')->firstOrFail()->amount);
    }

    public function test_baska_sitenin_dairesi_aidat_hedefi_olamaz(): void
    {
        $other = $this->site(1, $this->manager);
        $definition = $this->site->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);

        $this->put(route('site.dues.update', [$this->site, $definition]), [
            'name' => 'X', 'amount' => 700, 'calculation' => 'fixed', 'target_type' => 'unit', 'unit_id' => $this->unit($other)->id, 'due_day' => 15,
        ])->assertSessionHasErrors('unit_id');
    }

    public function test_duyuru_duzenlenir(): void
    {
        $announcement = $this->site->announcements()->create(['title' => 'Eski', 'content' => 'x']);

        $this->put(route('site.announcements.update', [$this->site, $announcement]), ['title' => 'Yeni', 'content' => 'y', 'priority' => 'high'])->assertSessionHas('status');

        $this->assertSame('Yeni', $announcement->fresh()->title);
        $this->assertSame('UPDATE_ANNOUNCEMENT', AuditLog::latest('id')->first()->action);
    }

    public function test_karar_no_kendisiyle_cakismaz_baskasiyla_cakisir(): void
    {
        $form = ['decided_on' => '2026-03-01', 'subject' => 'Yeni', 'content' => 'x'];
        $first = $this->site->decisions()->create($form + ['decision_no' => '2026/001']);
        $this->site->decisions()->create($form + ['decision_no' => '2026/002']);

        $this->put(route('site.decisions.update', [$this->site, $first]), $form + ['decision_no' => '2026/001'])->assertSessionHasNoErrors();
        $this->put(route('site.decisions.update', [$this->site, $first]), $form + ['decision_no' => '2026/002'])->assertSessionHasErrors('decision_no');
    }

    public function test_oysuz_anketin_sorusu_ve_secenekleri_degisir(): void
    {
        $poll = $this->site->polls()->create(['question' => 'Eski?']);
        $poll->options()->create(['text' => 'A']);
        $poll->options()->create(['text' => 'B']);

        $this->put(route('site.polls.update', [$this->site, $poll]), ['question' => 'Yeni?', 'options' => "X\nY\nZ"])->assertSessionHas('status');

        $this->assertSame('Yeni?', $poll->fresh()->question);
        $this->assertSame(['X', 'Y', 'Z'], $poll->options()->orderBy('id')->pluck('text')->all());
    }

    public function test_tek_secenekle_anket_guncellenmez(): void
    {
        $poll = $this->site->polls()->create(['question' => 'Eski?']);
        $poll->options()->create(['text' => 'A']);

        $this->put(route('site.polls.update', [$this->site, $poll]), ['question' => 'Yeni?', 'options' => 'X'])->assertSessionHas('error');

        $this->assertSame('Eski?', $poll->fresh()->question);
    }

    public function test_oy_kullanilmis_ankette_yalnizca_bitis_tarihi_degisir(): void
    {
        $poll = $this->site->polls()->create(['question' => 'Eski?']);
        $option = $poll->options()->create(['text' => 'A']);
        $poll->votes()->create(['poll_option_id' => $option->id, 'unit_id' => $this->flat->id, 'user_id' => $this->manager->id]);

        $this->put(route('site.polls.update', [$this->site, $poll]), ['question' => 'Yeni?', 'options' => "X\nY", 'due_date' => '2026-04-01']);

        $poll->refresh();
        $this->assertSame('Eski?', $poll->question);
        $this->assertSame('2026-04-01', $poll->due_date->toDateString());
        $this->assertSame(['A'], $poll->options()->pluck('text')->all());
    }

    public function test_blok_yeniden_adlandirilir_ayni_ad_reddedilir(): void
    {
        $first = $this->site->blocks()->first();
        $second = $this->site->blocks()->create(['name' => 'B']);

        $this->put(route('site.blocks.update', [$this->site, $second]), ['name' => 'c'])->assertSessionHasNoErrors();
        $this->assertSame('C', $second->fresh()->name);

        $this->put(route('site.blocks.update', [$this->site, $second]), ['name' => $first->name])->assertSessionHasErrors('name');
    }

    public function test_grup_uyeleri_guncellenir_ve_kaydedilir(): void
    {
        $neighbour = $this->unit($this->site, 1);
        $group = $this->site->unitGroups()->create(['name' => 'Eski']);
        $group->units()->sync([$this->flat->id]);

        $this->put(route('site.groups.update', [$this->site, $group]), ['name' => 'Yeni', 'unit_ids' => [$neighbour->id]])->assertSessionHas('status');

        $this->assertSame([$neighbour->id], $group->units()->pluck('units.id')->all());
        $log = AuditLog::where('action', 'UPDATE_UNIT_GROUP_MEMBERS')->firstOrFail();
        $this->assertSame([$neighbour->id], $log->data['attached']);
        $this->assertSame([$this->flat->id], $log->data['detached']);
    }

    public function test_gruba_baska_sitenin_dairesi_eklenemez(): void
    {
        $other = $this->site(1, $this->manager);
        $group = $this->site->unitGroups()->create(['name' => 'G']);

        $this->put(route('site.groups.update', [$this->site, $group]), ['name' => 'G', 'unit_ids' => [$this->unit($other)->id]])->assertSessionHasErrors('unit_ids.0');
    }

    public function test_stok_bilgisi_duzenlenir_miktar_degismez_hareket_kaydedilir(): void
    {
        $item = $this->site->inventoryItems()->create(['name' => 'Eski', 'category' => 'Ofis', 'quantity' => 5, 'unit' => 'adet', 'min_stock' => 1, 'location' => 'Depo']);

        $this->put(route('site.inventory.update', [$this->site, $item]), ['name' => 'Yeni', 'category' => 'Teknik', 'unit' => 'kutu', 'min_stock' => 2, 'location' => 'Ofis', 'quantity' => 999])->assertSessionHas('status');
        $this->assertSame('Yeni', $item->fresh()->name);
        $this->assertSame(5, (int) $item->fresh()->quantity);

        $this->patch(route('site.inventory.adjust', [$this->site, $item]), ['delta' => -2])->assertSessionHas('status');
        $this->assertSame(-2, AuditLog::where('action', 'ADJUST_STOCK')->firstOrFail()->data['delta']);
    }

    public function test_sayac_bilgisi_duzenlenir_endeks_degismez(): void
    {
        $meter = $this->meter();

        $this->put(route('site.meters.update', [$this->site, $meter]), ['name' => 'Yeni', 'type' => 'Elektrik', 'unit_id' => '', 'last_value' => 5])->assertSessionHas('status');

        $this->assertSame('Yeni', $meter->fresh()->name);
        $this->assertNull($meter->fresh()->unit_id);
        $this->assertEquals(100, (float) $meter->fresh()->last_value);
    }

    public function test_yeni_okuma_son_okumadan_eski_tarihli_olamaz(): void
    {
        $meter = $this->meter();
        $this->reading($meter, 120, '2026-03-10');

        $this->post(route('site.meters.readings', [$this->site, $meter]), ['value' => 130, 'read_on' => '2026-03-01'])->assertSessionHas('error');

        $this->assertSame(1, $meter->readings()->count());
        $this->assertEquals(120, (float) $meter->fresh()->last_value);
    }

    public function test_son_okuma_duzeltilir_tuketim_ve_endeks_yeniden_hesaplanir(): void
    {
        $meter = $this->meter();
        $this->reading($meter, 120);
        $last = $this->reading($meter, 150, '2026-03-10');

        $this->put(route('site.meters.readings.update', [$this->site, $meter, $last]), ['value' => 130, 'read_on' => '2026-03-11'])->assertSessionHas('status');

        $this->assertEquals(10, (float) $last->fresh()->consumption);
        $this->assertEquals(130, (float) $meter->fresh()->last_value);
    }

    public function test_son_okuma_oncekinden_kucuk_olamaz_eski_okuma_degismez(): void
    {
        $meter = $this->meter();
        $old = $this->reading($meter, 120);
        $last = $this->reading($meter, 150, '2026-03-10');

        $this->put(route('site.meters.readings.update', [$this->site, $meter, $last]), ['value' => 110, 'read_on' => '2026-03-10'])->assertSessionHas('error');
        $this->put(route('site.meters.readings.update', [$this->site, $meter, $old]), ['value' => 125, 'read_on' => '2026-03-01'])->assertSessionHas('error');
        $this->delete(route('site.meters.readings.destroy', [$this->site, $meter, $old]))->assertSessionHas('error');

        $this->assertEquals(150, (float) $meter->fresh()->last_value);
        $this->assertNotNull($old->fresh());
    }

    public function test_son_okuma_silinince_endeks_geri_doner(): void
    {
        $meter = $this->meter();
        $only = $this->reading($meter, 120);

        $this->delete(route('site.meters.readings.destroy', [$this->site, $meter, $only]))->assertSessionHas('status');

        $this->assertNull($only->fresh());
        $this->assertEquals(100, (float) $meter->fresh()->last_value);
    }

    public function test_faturalanmis_okuma_degistirilemez(): void
    {
        $meter = $this->meter();
        $reading = $this->reading($meter, 120);
        $this->bill('consumption');

        $this->put(route('site.meters.readings.update', [$this->site, $meter, $reading]), ['value' => 130, 'read_on' => '2026-03-01'])->assertSessionHas('error');
        $this->delete(route('site.meters.readings.destroy', [$this->site, $meter, $reading]))->assertSessionHas('error');

        $this->assertEquals(120, (float) $meter->fresh()->last_value);
    }

    public function test_baska_sayacin_okumasi_bu_sayactan_acilamaz(): void
    {
        $meter = $this->meter();
        $reading = $this->reading($this->meter(), 120);

        $this->delete(route('site.meters.readings.destroy', [$this->site, $meter, $reading]))->assertNotFound();
    }

    public function test_super_admin_kullanici_bilgilerini_duzenler(): void
    {
        $user = User::factory()->create(['email' => 'eski@test.com']);
        User::factory()->create(['email' => 'dolu@test.com']);

        $this->put(route('users.update', $user), ['name' => 'Yeni', 'email' => 'yeni@test.com', 'phone' => '555'])->assertSessionHas('status');
        $this->assertSame('yeni@test.com', $user->fresh()->email);

        $this->put(route('users.update', $user), ['name' => 'Yeni', 'email' => 'dolu@test.com'])->assertSessionHasErrors('email');
        $this->assertSame('yeni@test.com', $user->fresh()->email);
    }

    public function test_normal_admin_kullanici_bilgisi_degistiremez(): void
    {
        $user = User::factory()->create(['name' => 'Eski']);

        $this->actingAs($this->admin())->put(route('users.update', $user), ['name' => 'Yeni', 'email' => 'y@test.com'])->assertForbidden();

        $this->assertSame('Eski', $user->fresh()->name);
    }

    public function test_bakiyeli_kasa_pasife_alinamaz(): void
    {
        $box = $this->cashbox($this->site);
        $this->site->cashboxes()->create(['name' => 'İkinci', 'type' => 'cash']);
        $this->finance->generalTransaction($box, 'income', 10000, 'Bağış');

        $this->patch(route('site.cashboxes.toggle', [$this->site, $box]))->assertSessionHas('error');

        $this->assertTrue($box->fresh()->is_active);
    }

    public function test_son_aktif_kasa_pasife_alinamaz_ve_silinemez(): void
    {
        $box = $this->cashbox($this->site);

        $this->patch(route('site.cashboxes.toggle', [$this->site, $box]))->assertSessionHas('error');
        $this->delete(route('site.cashboxes.destroy', [$this->site, $box]))->assertSessionHas('error');

        $this->assertTrue($box->fresh()->is_active);
    }

    public function test_pasif_kasaya_islem_yapilamaz_ve_listede_cikmaz_tekrar_acilir(): void
    {
        $box = $this->cashbox($this->site);
        $passive = $this->site->cashboxes()->create(['name' => 'Eski Kasa', 'type' => 'cash']);
        $this->debt();

        $this->patch(route('site.cashboxes.toggle', [$this->site, $passive]))->assertSessionHas('status');
        $this->assertFalse($passive->fresh()->is_active);

        $this->get(route('site.collections.index', [$this->site, 'unit_id' => $this->flat->id]))->assertOk()->assertDontSee('Eski Kasa');
        $this->get(route('site.cashboxes.index', $this->site))->assertOk()->assertSee('Eski Kasa')->assertSee('Pasif');

        foreach ([
            fn () => $this->finance->collect($this->flat, $passive->fresh(), 1000),
            fn () => $this->finance->generalTransaction($passive->fresh(), 'income', 1000, 'x'),
            fn () => $this->finance->transfer($box, $passive->fresh(), 1000),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Pasif kasaya işlem yapıldı.');
            } catch (FinanceException $e) {
                $this->assertStringContainsString('pasif', $e->getMessage());
            }
        }

        $this->patch(route('site.cashboxes.toggle', [$this->site, $passive]))->assertSessionHas('status');
        $this->assertTrue($passive->fresh()->is_active);
    }

    public function test_hareket_gormemis_kasa_silinir_hareketli_silinmez(): void
    {
        $box = $this->cashbox($this->site);
        $unused = $this->site->cashboxes()->create(['name' => 'Boş', 'type' => 'cash']);
        $this->finance->generalTransaction($box, 'income', 1000, 'x');

        $this->delete(route('site.cashboxes.destroy', [$this->site, $box]))->assertSessionHas('error');
        $this->delete(route('site.cashboxes.destroy', [$this->site, $unused]))->assertSessionHas('status');

        $this->assertNotNull($box->fresh());
        $this->assertNull($unused->fresh());
    }

    public function test_kategori_adi_degisir_ayni_ad_reddedilir(): void
    {
        $first = $this->site->categories()->create(['name' => 'Bir', 'type' => 'income']);
        $second = $this->site->categories()->create(['name' => 'İki', 'type' => 'income']);
        $otherType = $this->site->categories()->create(['name' => 'Bir', 'type' => 'expense']);

        $this->put(route('site.categories.update', [$this->site, $second]), ['name' => 'Üç'])->assertSessionHasNoErrors();
        $this->put(route('site.categories.update', [$this->site, $second]), ['name' => 'Bir'])->assertSessionHasErrors('name');
        $this->put(route('site.categories.update', [$this->site, $otherType]), ['name' => 'Bir'])->assertSessionHasNoErrors();

        $this->assertSame('Bir', $first->fresh()->name);
        $this->assertSame('Üç', $second->fresh()->name);
    }

    public function test_kullanilmis_kategori_silinmez_kullanilmamis_silinir(): void
    {
        $used = $this->site->categories()->create(['name' => 'Kullanılan', 'type' => 'income']);
        $this->finance->accrue($this->flat, ['description' => 'x', 'amount' => 100, 'due_date' => '2026-04-01', 'category_id' => $used->id, 'category_name' => $used->name]);
        Debt::query()->delete();
        $free = $this->site->categories()->create(['name' => 'Boş', 'type' => 'income']);

        $this->delete(route('site.categories.destroy', [$this->site, $used]))->assertSessionHas('error');
        $this->delete(route('site.categories.destroy', [$this->site, $free]))->assertSessionHas('status');

        $this->assertNotNull($used->fresh());
        $this->assertNull($free->fresh());
    }

    public function test_sayac_faturasi_iptali_tum_paylari_ters_kayitla_siler(): void
    {
        $bill = $this->bill();
        $this->assertSame(2, Debt::where('distribution_id', "meter:{$bill->id}")->count());

        $this->delete(route('site.meter-bills.destroy', [$this->site, $bill]))->assertSessionHas('status');

        $this->assertSame(0, Debt::count());
        $this->assertNull(MeterBill::find($bill->id));
        $this->assertLedgerBalanced($this->site);
        $this->assertEquals(0, (float) $this->site->ledgerEntries()->where('account_name', 'Üye/Daire Hesabı')->selectRaw('SUM(debit) - SUM(credit) n')->value('n'));
        $this->assertSame(2, AuditLog::where('action', 'CANCEL_METER_BILL')->firstOrFail()->data['units']);
    }

    public function test_tahsilat_alinmis_fatura_iptal_edilemez_hicbir_pay_silinmez(): void
    {
        $bill = $this->bill();
        $this->finance->collect($this->flat, $this->cashbox($this->site), 1000);

        $this->delete(route('site.meter-bills.destroy', [$this->site, $bill]))->assertSessionHas('error');

        $this->assertSame(2, Debt::count());
        $this->assertNotNull($bill->fresh());
    }

    public function test_kapali_donem_faturasi_iptal_edilemez(): void
    {
        $bill = $this->bill();
        $this->travelTo('2026-04-20 12:00');
        $this->site->update(['closed_until' => '2026-03-31']);

        $this->delete(route('site.meter-bills.destroy', [$this->site, $bill]))->assertSessionHas('error');

        $this->assertSame(2, Debt::count());
    }

    public function test_muhasebeci_finans_kaydini_duzenler_operasyonu_duzenleyemez(): void
    {
        $accountant = $this->admin();
        $this->site->managers()->attach($accountant, ['role' => 'accountant']);
        $debt = $this->debt();
        $announcement = $this->site->announcements()->create(['title' => 'Eski', 'content' => 'x']);

        $this->actingAs($accountant)->put(route('site.debts.update', [$this->site, $debt]), $this->debtForm())->assertSessionHas('status');
        $this->actingAs($accountant)->put(route('site.announcements.update', [$this->site, $announcement]), ['title' => 'Yeni', 'content' => 'y', 'priority' => 'normal'])->assertForbidden();
    }

    public function test_duzenleme_ekranlari_acilir(): void
    {
        $this->debt();
        $this->site->expenses()->create(['description' => 'Elektrik', 'amount' => 100, 'total_amount' => 120]);
        $this->site->duesDefinitions()->create(['name' => 'Aidat', 'amount' => 500, 'calculation' => 'fixed']);
        $this->site->announcements()->create(['title' => 'D', 'content' => 'x']);
        $this->site->decisions()->create(['decision_no' => '1', 'decided_on' => '2026-03-01', 'subject' => 'K', 'content' => 'x']);
        $this->site->polls()->create(['question' => 'S?']);
        $this->site->inventoryItems()->create(['name' => 'Kalem', 'category' => 'Ofis', 'quantity' => 1, 'unit' => 'adet', 'min_stock' => 1, 'location' => 'D']);
        $this->reading($this->meter(), 120);
        $this->bill();
        $this->site->unitGroups()->create(['name' => 'G']);

        foreach (['debts', 'expenses', 'dues', 'announcements', 'decisions', 'polls', 'inventory', 'meters', 'meter-bills', 'units', 'cashboxes'] as $page) {
            $this->get(route("site.{$page}.index", $this->site))->assertOk()->assertSee(['Düzenle', 'İptal et'][$page === 'meter-bills' ? 1 : 0]);
        }
        $this->get(route('users.show', User::factory()->create()))->assertOk();
    }
}
