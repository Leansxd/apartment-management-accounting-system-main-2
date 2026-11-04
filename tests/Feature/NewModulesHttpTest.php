<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewModulesHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Apartment $apt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@demo.test')->first();
        $this->apt = Apartment::first();
    }

    public function test_tum_yeni_sayfalar_acilir(): void
    {
        $a = $this->apt;
        $meeting = $a->meetings()->first();
        $pages = [
            route('site.late-fees.index', $a), route('site.advances.index', $a), route('site.bank.index', $a),
            route('site.budget.index', $a), route('site.accounting.trial-balance', $a), route('site.accounting.cash-book', $a),
            route('site.accounting.balance-sheet', $a), route('site.accounting.aging', $a), route('site.payroll.index', $a),
            route('site.assets.index', $a), route('site.meter-bills.index', $a), route('site.reservations.index', $a),
            route('site.meetings.index', $a), route('site.meetings.show', [$a, $meeting]), route('site.documents.index', $a),
            route('site.members.history', $a), route('site.reports.index', $a),
            route('site.transactions.receipt', [$a, CashTransaction::first()]),
            route('site.accounting.cash-book', [$a, 'cashbox_id' => 2, 'from' => '2020-01-01']),
            route('site.accounting.aging', [$a, 'as_of' => '2027-01-01']),
        ];
        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_gecikme_zammi_ayari_onizleme_ve_yazma(): void
    {
        $this->actingAs($this->admin)->put(route('site.late-fees.settings', $this->apt), ['late_fee_enabled' => 1, 'late_fee_rate' => 5, 'late_fee_grace_days' => 0])->assertSessionHas('status');
        $before = Debt::where('kind', 'late_fee')->count();

        $this->actingAs($this->admin)->post(route('site.late-fees.run', $this->apt), ['as_of' => today()->toDateString()])->assertSessionHas('status');
        $this->assertGreaterThan($before, Debt::where('kind', 'late_fee')->count());

        $count = Debt::where('kind', 'late_fee')->count();
        $this->actingAs($this->admin)->post(route('site.late-fees.run', $this->apt), ['as_of' => today()->toDateString()]);
        $this->assertSame($count, Debt::where('kind', 'late_fee')->count()); // mükerrer yok
        $this->assertLedgerBalanced($this->apt);
    }

    public function test_tahsilat_iptal_ve_makbuz_http(): void
    {
        $tx = CashTransaction::where('sub_type', 'collection')->first();
        $this->actingAs($this->admin)->get(route('site.transactions.receipt', [$this->apt, $tx]))->assertOk()->assertSee($tx->receiptNo());

        $this->actingAs($this->admin)->post(route('site.transactions.reverse', [$this->apt, $tx]), ['reason' => 'Test iptali'])->assertSessionHas('status');
        $this->assertTrue($tx->fresh()->isReversed());
        $this->actingAs($this->admin)->post(route('site.transactions.reverse', [$this->apt, $tx]), ['reason' => 'tekrar'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('site.transactions.reverse', [$this->apt, $tx]), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertLedgerBalanced($this->apt);
    }

    public function test_taksitlendirme_icra_ve_avans_iadesi_http(): void
    {
        $debt = Debt::where('status', 'pending')->whereNull('legal_status')->first();
        $this->actingAs($this->admin)->post(route('site.debts.installments', [$this->apt, $debt]), ['count' => 3, 'first_due' => today()->addMonth()->toDateString(), 'every_months' => 1])->assertSessionHas('status');
        $this->assertSame(3, Debt::where('parent_debt_id', $debt->id)->count());

        $other = Debt::where('status', 'pending')->whereNull('legal_status')->where('kind', 'due')->first();
        $this->actingAs($this->admin)->post(route('site.debts.legal', [$this->apt, $other]), ['note' => 'Dosya 1'])->assertSessionHas('status');
        $this->assertSame('icra', $other->fresh()->legal_status);

        $unit = $this->apt->units()->where('credit_balance', '>', 0)->first();
        $box = $this->apt->cashboxes()->orderBy('id')->first();
        $this->actingAs($this->admin)->post(route('site.advances.refund', [$this->apt, $unit]), ['cashbox_id' => $box->id, 'amount' => '1.00'])->assertSessionHas('status');
    }

    public function test_banka_ekstresi_yukleme_ve_isleme_http(): void
    {
        $bank = $this->apt->cashboxes()->where('type', 'bank')->first();
        $unit = $this->apt->units()->with('block')->first();
        $csv = "Tarih;Açıklama;Tutar\n".today()->format('d.m.Y').';AIDAT '.$unit->label().";1.500,00\n";
        $file = UploadedFile::fake()->createWithContent('ekstre.csv', $csv);

        $this->actingAs($this->admin)->post(route('site.bank.import', $this->apt), ['cashbox_id' => $bank->id, 'file' => $file])->assertSessionHas('status');
        $line = BankStatementLine::first();
        $this->assertSame($unit->id, $line->unit_id); // otomatik eşleşti

        $this->actingAs($this->admin)->post(route('site.bank.post-matched', $this->apt))->assertSessionHas('status');
        $this->assertSame('posted', $line->fresh()->status);
    }

    public function test_butce_kaydi_ve_bordro_http(): void
    {
        $category = $this->apt->categories()->where('type', 'expense')->first();
        $this->actingAs($this->admin)->post(route('site.budget.save', $this->apt), ['year' => 2027, 'amounts' => [$category->id => '5000']])->assertRedirect();
        $this->assertEquals(5000, (float) $this->apt->budgetItems()->where('year', 2027)->value('amount'));
        $this->actingAs($this->admin)->post(route('site.budget.save', $this->apt), ['year' => 2027, 'amounts' => [$category->id => '']]);
        $this->assertSame(0, $this->apt->budgetItems()->where('year', 2027)->count());

        $this->actingAs($this->admin)->post(route('site.payroll.generate', $this->apt), ['period' => '2027-01'])->assertSessionHas('status');
        $this->assertSame(1, $this->apt->expenses()->where('period', '2027-01')->count());
    }

    public function test_demirbas_rezervasyon_toplanti_http(): void
    {
        $a = $this->apt;
        $this->actingAs($this->admin)->post(route('site.assets.store', $a), ['name' => 'Pompa', 'category' => 'Diğer', 'purchase_date' => today()->subYear()->toDateString(), 'cost' => 6000, 'useful_life_years' => 6])->assertSessionHasNoErrors();
        $asset = $a->assets()->where('name', 'Pompa')->first();
        $this->assertEquals(0, (float) $asset->salvage_value);
        $this->actingAs($this->admin)->post(route('site.assets.dispose', [$a, $asset]), ['disposed_on' => today()->toDateString()])->assertSessionHas('status');
        $this->assertSame('disposed', $asset->fresh()->status);

        $area = $a->commonAreas()->first();
        $unit = $a->units()->first();
        $this->actingAs($this->admin)->post(route('site.reservations.store', $a), ['common_area_id' => $area->id, 'unit_id' => $unit->id, 'date' => today()->addDays(9)->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00'])->assertSessionHas('status');
        $res = Reservation::latest('id')->first();
        $this->assertSame('approved', $res->status);
        $this->actingAs($this->admin)->post(route('site.reservations.cancel', [$a, $res]))->assertSessionHas('status');

        $meeting = Meeting::first();
        $ids = $a->units()->pluck('id')->take(7)->all();
        $this->actingAs($this->admin)->post(route('site.meetings.attendance', [$a, $meeting]), ['present' => $ids, 'proxy' => [$ids[0] => 1], 'name' => [$ids[0] => 'Vekil Kişi']])->assertSessionHas('status');
        $this->assertSame(7, $meeting->attendances()->count());
        $this->assertTrue($meeting->attendances()->where('unit_id', $ids[0])->first()->is_proxy);
        $this->actingAs($this->admin)->get(route('site.meetings.show', [$a, $meeting]))->assertSee('Sağlandı'); // 7 / 12 daire ve eşit arsa payı: sayı ve pay çoğunluğu var
    }

    public function test_belge_yukleme_indirme_ve_silme(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('sozlesme.pdf', 100, 'application/pdf');
        $this->actingAs($this->admin)->post(route('site.documents.store', $this->apt), ['title' => 'Test sözleşmesi', 'category' => 'Sözleşme', 'file' => $file])->assertSessionHas('status');
        $doc = Document::where('title', 'Test sözleşmesi')->first();
        Storage::disk('local')->assertExists($doc->file_path);

        $this->actingAs($this->admin)->get(route('site.documents.download', [$this->apt, $doc]))->assertOk()->assertDownload('sozlesme.pdf');

        $other = $this->admin(); // başka sitenin yöneticisi indiremez
        $this->site(1, $other);
        $this->actingAs($other)->get(route('site.documents.download', [$this->apt, $doc]))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('site.documents.destroy', [$this->apt, $doc]))->assertSessionHas('status');
        Storage::disk('local')->assertMissing($doc->file_path);

        $bad = UploadedFile::fake()->create('virus.exe', 10);
        $this->actingAs($this->admin)->post(route('site.documents.store', $this->apt), ['title' => 'x', 'category' => 'Diğer', 'file' => $bad])->assertSessionHasErrors('file');
    }

    public function test_site_rolleri_izleyici_yazamaz_muhasebeci_yalniz_finans(): void
    {
        $viewer = $this->admin();
        $accountant = $this->admin();
        $this->apt->managers()->attach($viewer->id, ['role' => 'viewer']);
        $this->apt->managers()->attach($accountant->id, ['role' => 'accountant']);
        $unit = $this->apt->units()->first();
        $box = $this->apt->cashboxes()->first();

        $this->actingAs($viewer)->get(route('site.finance', $this->apt))->assertOk();
        $this->actingAs($viewer)->post(route('site.collections.store', $this->apt), ['unit_id' => $unit->id, 'cashbox_id' => $box->id, 'amount' => 10, 'method' => 'collection'])->assertForbidden();

        $this->actingAs($accountant)->post(route('site.collections.store', $this->apt), ['unit_id' => $unit->id, 'cashbox_id' => $box->id, 'amount' => 10, 'method' => 'collection'])->assertSessionHas('status');
        $this->actingAs($accountant)->post(route('site.announcements.store', $this->apt), ['title' => 'x', 'content' => 'y', 'priority' => 'normal'])->assertForbidden();
        $this->actingAs($accountant)->post(route('site.blocks.store', $this->apt), ['name' => 'Z'])->assertForbidden();

        // Rol yönetimi yalnızca site sahibi / süper admin
        $this->actingAs($accountant)->patch(route('site.managers.role', [$this->apt, $viewer]), ['role' => 'manager'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('site.managers.role', [$this->apt, $viewer]), ['role' => 'accountant'])->assertSessionHas('status');
        $this->assertSame('accountant', $this->apt->managers()->whereKey($viewer->id)->first()->pivot->role);
    }

    public function test_uye_gecmise_tasinir_ve_gecmis_listede_gorunur(): void
    {
        $member = $this->apt->members()->whereNull('user_id')->first();
        $this->actingAs($this->admin)->delete(route('site.members.destroy', [$this->apt, $member]), ['moved_out_on' => today()->toDateString(), 'move_out_reason' => 'Satış'])->assertRedirect();
        $this->assertNotNull($member->fresh()?->deleted_at ?? \App\Models\Member::withTrashed()->find($member->id)->deleted_at);
        $this->actingAs($this->admin)->get(route('site.members.history', $this->apt))->assertOk()->assertSee($member->full_name)->assertSee('Satış');
    }

    public function test_bildirim_e_posta_secenegi_yalniz_e_postasi_olan_uyelere_gider(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $units = $this->apt->units()->limit(3)->get();
        $this->apt->members()->where('unit_id', $units[0]->id)->update(['email' => 'a@ornek.com']);
        $this->apt->members()->where('unit_id', $units[1]->id)->update(['email' => 'gecersiz-adres']);

        $this->actingAs($this->admin)->post(route('site.notifications.store', $this->apt), [
            'title' => 'Su kesintisi', 'body' => 'Yarın 10:00', 'target' => 'units', 'unit_ids' => $units->pluck('id')->all(), 'send_email' => 1,
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'E-posta: 1 gönderildi'));
    }
}
