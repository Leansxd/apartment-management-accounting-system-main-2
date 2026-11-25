<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ManualJournalTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    private TransactionCategory $income;

    private TransactionCategory $otherIncome;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-15 12:00');
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
        $this->income = $this->site->categories()->create(['name' => 'Aidat', 'type' => 'income']);
        $this->otherIncome = $this->site->categories()->create(['name' => 'Kira', 'type' => 'income']);
        $this->actingAs($this->manager);
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return $overrides + ['date' => '2026-03-10', 'description' => 'Sınıflandırma düzeltmesi', 'lines' => [
            ['account' => "income:{$this->income->id}", 'debit' => '100', 'credit' => ''],
            ['account' => "income:{$this->otherIncome->id}", 'debit' => '', 'credit' => '100'],
        ]];
    }

    private function post_(array $form): TestResponse
    {
        return $this->post(route('site.ledger.manual.store', $this->site), $form);
    }

    public function test_dengeli_fis_tarihiyle_yazilir_ve_kaydedilir(): void
    {
        $this->post_($this->form())->assertSessionHas('status');

        $entries = LedgerEntry::where('entry_type', 'manual')->get();
        $this->assertCount(2, $entries);
        $this->assertSame('2026-03-10', $entries->first()->created_at->toDateString());
        $this->assertSame((string) $this->income->id, $entries->first()->account_id);
        $this->assertLedgerBalanced($this->site);
        $this->assertSame($this->manager->id, AuditLog::where('action', 'MANUAL_JOURNAL')->firstOrFail()->user_id);
    }

    public function test_dengesiz_fis_reddedilir(): void
    {
        $form = $this->form();
        $form['lines'][1]['credit'] = '90';

        $this->post_($form)->assertSessionHas('error');

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_bos_satirlar_yok_sayilir_tek_satir_reddedilir(): void
    {
        $form = $this->form();
        $form['lines'][1] = ['account' => '', 'debit' => '', 'credit' => ''];

        $this->post_($form)->assertSessionHas('error');

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_ayni_satirda_borc_ve_alacak_olamaz(): void
    {
        $form = $this->form();
        $form['lines'][0]['credit'] = '100';

        $this->post_($form)->assertSessionHas('error');

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_kasa_ve_daire_hesabi_kullanilamaz(): void
    {
        foreach (['cash:1', 'unit:1', 'advance:1'] as $account) {
            $form = $this->form();
            $form['lines'][1]['account'] = $account;
            $this->post_($form)->assertSessionHas('error');
        }

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_baska_sitenin_kategorisi_ve_tur_uyumsuzlugu_reddedilir(): void
    {
        $other = $this->site(1, $this->manager)->categories()->create(['name' => 'Yabancı', 'type' => 'income']);
        $expense = $this->site->categories()->create(['name' => 'Gider', 'type' => 'expense']);

        foreach (["income:{$other->id}", "income:{$expense->id}"] as $account) {
            $form = $this->form();
            $form['lines'][1]['account'] = $account;
            $this->post_($form)->assertSessionHas('error');
        }

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_kapali_doneme_ve_gelecek_tarihe_fis_kesilemez(): void
    {
        $this->site->update(['closed_until' => '2026-03-10']);

        $this->post_($this->form())->assertSessionHas('error');
        $this->post_($this->form(['date' => '2026-03-16']))->assertSessionHasErrors('date');

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_fis_ters_kayitla_iptal_edilir_ikinci_kez_edilemez(): void
    {
        $this->post_($this->form());
        $reference = LedgerEntry::where('entry_type', 'manual')->value('reference');

        $this->put(route('site.ledger.manual.reverse', $this->site), ['reference' => $reference, 'reason' => 'Hatalı'])->assertSessionHas('status');
        $this->assertSame(2, LedgerEntry::where('reference', $reference)->where('entry_type', 'manual_reversal')->count());
        $this->assertEquals(0, (float) LedgerEntry::where('account_id', (string) $this->income->id)->selectRaw('SUM(debit) - SUM(credit) n')->value('n'));

        $this->put(route('site.ledger.manual.reverse', $this->site), ['reference' => $reference, 'reason' => 'Tekrar'])->assertSessionHas('error');
        $this->assertSame(2, LedgerEntry::where('reference', $reference)->where('entry_type', 'manual_reversal')->count());
    }

    public function test_otomatik_kayit_elle_iptal_edilemez(): void
    {
        LedgerEntry::create(['apartment_id' => $this->site->id, 'reference' => 'debt:1', 'entry_type' => 'accrual', 'account_name' => LedgerEntry::UNIT, 'debit' => 1, 'credit' => 0, 'description' => 'x']);

        $this->put(route('site.ledger.manual.reverse', $this->site), ['reference' => 'debt:1', 'reason' => 'x'])->assertSessionHas('error');

        $this->assertSame(1, LedgerEntry::count());
    }

    public function test_kapali_donemdeki_fis_iptal_edilemez(): void
    {
        $this->post_($this->form());
        $reference = LedgerEntry::where('entry_type', 'manual')->value('reference');
        $this->site->update(['closed_until' => '2026-03-12']);

        $this->put(route('site.ledger.manual.reverse', $this->site), ['reference' => $reference, 'reason' => 'x'])->assertSessionHas('error');

        $this->assertSame(0, LedgerEntry::where('entry_type', 'manual_reversal')->count());
    }

    public function test_muhasebeci_fis_keser_izleyici_kesemez(): void
    {
        $accountant = $this->admin();
        $viewer = $this->admin();
        $this->site->managers()->attach($accountant, ['role' => 'accountant']);
        $this->site->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->post_($this->form())->assertForbidden();
        $this->actingAs($accountant)->post_($this->form())->assertSessionHas('status');
    }

    public function test_mizanda_kategoriyle_gorunur_defter_sayfasi_acilir(): void
    {
        $this->post_($this->form());

        $this->get(route('site.ledger', $this->site))->assertOk()->assertSee('Sınıflandırma düzeltmesi')->assertSee('İptal et');
        $this->get(route('site.accounting.trial-balance', $this->site))->assertOk()->assertSee('Kira');
    }
}
