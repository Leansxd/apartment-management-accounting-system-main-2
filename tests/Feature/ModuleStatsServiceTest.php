<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\ModuleStatsService;
use App\Services\ReportService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const SITE_PAGES = [
        'site.dashboard', 'site.finance', 'site.debts.index', 'site.collections.index', 'site.cashboxes.index', 'site.expenses.index',
        'site.vendors.index', 'site.bank.index', 'site.dues.index', 'site.late-fees.index', 'site.advances.index', 'site.ledger',
        'site.income-expense', 'site.accounting.trial-balance', 'site.accounting.cash-book', 'site.accounting.balance-sheet',
        'site.accounting.aging', 'site.budget.index', 'site.units.index', 'site.members.index', 'site.members.history', 'site.audit',
        'site.tasks.index', 'site.tickets.index', 'site.security.index', 'site.staff.index', 'site.inventory.index', 'site.meters.index',
        'site.meter-bills.index', 'site.payroll.index', 'site.assets.index', 'site.documents.index', 'site.reservations.index',
        'site.announcements.index', 'site.polls.index', 'site.decisions.index', 'site.meetings.index', 'site.notifications.index', 'site.reminders.index',
    ];

    private function finance(): FinanceService
    {
        return app(FinanceService::class);
    }

    private function stats(): ModuleStatsService
    {
        return app(ModuleStatsService::class);
    }

    private function metric(array $summary, string $label): string
    {
        return collect($summary['metrics'])->firstWhere('label', $label)['value'];
    }

    private function debt(Apartment $apartment, int $amount, string $due, int $unitIndex = 0): void
    {
        $this->finance()->accrue($this->unit($apartment, $unitIndex), ['description' => 'Aidat', 'amount' => $amount, 'due_date' => $due]);
    }

    public function test_demo_verisinde_tum_sayfalar_ozetle_acilir(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@demo.test')->first();
        $apartment = Apartment::first();

        foreach (self::SITE_PAGES as $page) {
            $this->actingAs($admin)->get(route($page, $apartment))->assertOk()->assertSee('data-module-summary', false);
        }
        foreach ([route('dashboard'), route('users.index'), route('properties.index'), route('site.members.show', [$apartment, $apartment->members()->first()])] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('data-module-summary', false);
        }
    }

    public function test_verisi_olmayan_sitede_tum_sayfalar_hatasiz_acilir(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(0, $manager);

        foreach (self::SITE_PAGES as $page) {
            $this->actingAs($manager)->get(route($page, $apartment))->assertOk();
        }
        $this->actingAs($manager)->get(route('sites.index'))->assertOk()->assertSee('Gecikmiş');
    }

    public function test_borc_ozeti_acik_gecikmis_ve_tahsil_oranini_hesaplar(): void
    {
        $this->travelTo('2026-05-15');
        $apartment = $this->site(2);
        $this->debt($apartment, 100000, '2026-04-10');
        $this->debt($apartment, 50000, '2026-06-01', 1);
        $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 30000);

        $summary = $this->stats()->debts($apartment);

        $this->assertSame('1.200,00 ₺', $this->metric($summary, 'Açık borç'));
        $this->assertSame('700,00 ₺', $this->metric($summary, 'Gecikmiş'));
        $this->assertSame('%20,0', $this->metric($summary, 'Tahsil oranı'));
    }

    public function test_iptal_edilen_tahsilat_ozete_girmez(): void
    {
        $this->travelTo('2026-05-15');
        $apartment = $this->site(1);
        $this->debt($apartment, 100000, '2026-06-01');
        $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 20000);
        $reversed = $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 30000);

        $this->finance()->reverse($reversed, 'Hatalı giriş');

        $this->assertSame('200,00 ₺', $this->metric($this->stats()->collections($apartment), 'Bu ay tahsilat'));
    }

    public function test_ozet_baska_sitenin_kayitlarini_saymaz(): void
    {
        $this->travelTo('2026-05-15');
        $apartment = $this->site(1);
        $other = $this->site(1);
        $this->debt($other, 900000, '2026-04-10');

        $this->assertSame('0,00 ₺', $this->metric($this->stats()->debts($apartment), 'Açık borç'));
    }

    public function test_blok_karsilastirmasi_bloklari_ayri_hesaplar(): void
    {
        $this->travelTo('2026-05-15');
        $apartment = $this->site(1);
        $blockB = $apartment->blocks()->create(['name' => 'B']);
        $apartment->units()->create(['block_id' => $blockB->id, 'unit_no' => '1']);
        $this->debt($apartment, 100000, '2026-04-10');
        $this->debt($apartment, 40000, '2026-04-10', 1);

        $this->assertSame(
            ['A' => 1000.0, 'B' => 400.0],
            app(ReportService::class)->blockComparison($apartment)->pluck('overdue', 'block')->all(),
        );
    }

    public function test_site_listesi_site_basina_tahsilat_ve_gecikmeyi_gosterir(): void
    {
        $this->travelTo('2026-05-15');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $this->debt($apartment, 100000, '2026-04-10');
        $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 25000);

        $this->actingAs($manager)->get(route('sites.index'))->assertOk()->assertSee('%25,0')->assertSee('750,00 ₺');
    }

    public function test_daire_listesi_acik_borcu_gosterir_ve_borclulari_suzer(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(2, $manager);
        $this->debt($apartment, 100000, '2026-04-10');

        $this->actingAs($manager)->get(route('site.units.index', [$apartment, 'debtors' => 1]))
            ->assertOk()
            ->assertSee('1.000,00 ₺')
            ->assertViewHas('units', fn ($units) => $units->pluck('id')->all() === [$this->unit($apartment)->id]);
    }

    public function test_borc_filtreleri_blok_tur_ve_vadeye_gore_suzer(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $blockB = $apartment->blocks()->create(['name' => 'B']);
        $apartment->units()->create(['block_id' => $blockB->id, 'unit_no' => '1']);
        $this->debt($apartment, 10000, '2026-01-10');
        $this->debt($apartment, 20000, '2026-03-10', 1);

        $amounts = fn (array $query) => $this->actingAs($manager)->get(route('site.debts.index', [$apartment, ...$query]))
            ->assertOk()->viewData('debts')->pluck('amount')->all();

        $this->assertSame(['200.00'], $amounts(['block_id' => $blockB->id]));
        $this->assertSame(['100.00'], $amounts(['to' => '2026-02-01']));
        $this->assertSame([], $amounts(['kind' => 'late_fee']));
    }

    public function test_gecersiz_tarih_filtresi_yok_sayilir(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $this->debt($apartment, 10000, '2026-01-10');

        $this->actingAs($manager)->get(route('site.debts.index', [$apartment, 'from' => 'gecersiz']))
            ->assertOk()
            ->assertViewHas('debts', fn ($debts) => $debts->count() === 1);
    }

    public function test_gorev_gecikme_filtresi_tamamlananlari_dislar(): void
    {
        $this->travelTo('2026-05-15');
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $late = $apartment->tasks()->create(['title' => 'Geciken', 'priority' => 'high', 'category' => 'other', 'status' => 'pending', 'due_date' => '2026-05-01']);
        $apartment->tasks()->create(['title' => 'Biten', 'priority' => 'low', 'category' => 'other', 'status' => 'completed', 'due_date' => '2026-05-01']);

        $this->actingAs($manager)->get(route('site.tasks.index', [$apartment, 'overdue' => 1]))
            ->assertViewHas('tasks', fn ($tasks) => $tasks->pluck('id')->all() === [$late->id]);
    }

    public function test_islem_kayitlari_module_gore_suzulur(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $this->finance()->audit($apartment, $manager, 'COLLECTION', 'FINANCE', 'a');
        $this->finance()->audit($apartment, $manager, 'PAYROLL', 'EXPENSE', 'b');

        $this->actingAs($manager)->get(route('site.audit', [$apartment, 'module' => 'EXPENSE']))
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('reference')->all() === ['b']);
    }

    public function test_sakinler_mobil_baglantiya_gore_suzulur(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(1, $manager);
        $unit = $this->unit($apartment);
        $linked = $apartment->members()->create(['unit_id' => $unit->id, 'type' => 'owner', 'full_name' => 'Bağlı Sakin', 'user_id' => User::factory()->create()->id]);
        $apartment->members()->create(['unit_id' => $unit->id, 'type' => 'tenant', 'full_name' => 'Bekleyen Sakin']);

        $this->actingAs($manager)->get(route('site.members.index', [$apartment, 'mobile' => 'linked']))
            ->assertViewHas('members', fn ($members) => $members->pluck('id')->all() === [$linked->id]);
    }

    public function test_izleyici_ozetleri_gorur_baska_sitenin_yoneticisi_goremez(): void
    {
        $apartment = $this->site(1);
        $viewer = $this->admin();
        $apartment->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->get(route('site.debts.index', $apartment))->assertOk()->assertSee('data-module-summary', false);
        $this->actingAs($this->admin())->get(route('site.debts.index', $apartment))->assertForbidden();
    }

    public function test_acik_borc_sayimi_odenen_borcu_disarida_birakir(): void
    {
        $this->travelTo('2026-05-15');
        $apartment = $this->site(1);
        $this->debt($apartment, 10000, '2026-04-10');
        $this->finance()->collect($this->unit($apartment), $this->cashbox($apartment), 10000);

        $this->assertSame(Debt::PAID, $apartment->debts()->first()->status);
        $this->assertSame('0,00 ₺', $this->metric($this->stats()->debts($apartment), 'Açık borç'));
        $this->assertSame('—', collect($this->stats()->debts($this->site(1))['metrics'])->firstWhere('label', 'Tahsil oranı')['value']);
    }
}
