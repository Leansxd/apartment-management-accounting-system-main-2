<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Apartment $apartment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@demo.test')->first();
        $this->apartment = Apartment::first();
    }

    public function test_tum_panel_sayfalari_acilir(): void
    {
        $a = $this->apartment;
        $member = $a->members()->first();
        $pages = [
            route('dashboard'), route('sites.index'), route('users.index'), route('users.show', $this->admin),
            route('properties.index'), route('properties.create'),
            route('site.dashboard', $a), route('site.dashboard', [$a, 'from' => '2026-01-01', 'to' => '2026-12-31']),
            route('site.settings', $a), route('site.audit', $a), route('site.units.index', $a),
            route('site.members.index', $a), route('site.members.show', [$a, $member]),
            route('site.finance', $a), route('site.debts.index', $a), route('site.debts.index', [$a, 'status' => 'overdue']),
            route('site.collections.index', $a), route('site.collections.index', [$a, 'unit_id' => $member->unit_id]),
            route('site.cashboxes.index', $a), route('site.ledger', $a), route('site.income-expense', $a),
            route('site.statements', $a), route('site.statements', [$a, 'unit_id' => $member->unit_id]),
            route('site.expenses.index', $a), route('site.vendors.index', $a), route('site.dues.index', $a),
            route('site.tasks.index', $a), route('site.staff.index', $a), route('site.inventory.index', $a),
            route('site.meters.index', $a), route('site.security.index', $a), route('site.security.index', [$a, 'kind' => 'parking']),
            route('site.tickets.index', $a), route('site.announcements.index', $a), route('site.polls.index', $a),
            route('site.decisions.index', $a), route('site.notifications.index', $a), route('site.reminders.index', $a),
            route('site.reports.index', $a),
        ];
        foreach (array_keys(ReportController::TYPES) as $type) {
            $pages[] = route('site.reports.show', [$a, $type]);
        }

        foreach ($pages as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($this->admin)->get(route('site.reports.show', [$a, 'balance', 'format' => 'csv']))
            ->assertOk()->assertDownload();
    }

    public function test_misafir_girise_sakin_panele_yonlendirilir(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $resident = User::where('email', 'sakin@demo.test')->first();
        $this->actingAs($resident)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_baska_sitenin_yoneticisi_bu_siteye_erisemez(): void
    {
        $other = $this->admin(); // süper admin olmayan, başka bir sitenin yöneticisi
        $this->site(1, $other);

        $this->actingAs($other)->get(route('site.dashboard', $this->apartment))->assertForbidden();
        $this->actingAs($other)->post(route('site.collections.store', $this->apartment), [])->assertForbidden();
        $this->actingAs($other)->get(route('users.show', User::where('email', 'sakin@demo.test')->first()))->assertNotFound();
    }

    public function test_kayit_baska_siteye_aitse_url_ile_erisilemez(): void
    {
        $mine = $this->site(1, $this->admin);
        $foreignDebt = Debt::first(); // demo sitesinin borcu

        $this->actingAs($this->admin)->delete(route('site.debts.destroy', [$mine, $foreignDebt]))->assertNotFound();
        $this->assertNotNull($foreignDebt->fresh());
    }

    public function test_panelden_tahsilat_ve_virman(): void
    {
        $a = $this->apartment;
        $unit = $a->units()->first();
        [$kasa, $banka] = $a->cashboxes()->orderBy('id')->get();
        $before = (float) $kasa->balance;

        $this->actingAs($this->admin)->post(route('site.collections.store', $a), [
            'unit_id' => $unit->id, 'cashbox_id' => $kasa->id, 'amount' => '250.50', 'method' => 'collection',
        ])->assertSessionHas('status');
        $this->assertEquals($before + 250.50, (float) $kasa->fresh()->balance);

        $this->actingAs($this->admin)->post(route('site.transfers.store', $a), [
            'from_id' => $kasa->id, 'to_id' => $banka->id, 'amount' => 999999,
        ])->assertSessionHas('error'); // yetersiz bakiye
    }

    public function test_yalnizca_super_admin_rol_degistirir(): void
    {
        $plain = $this->admin();
        $resident = User::where('email', 'sakin@demo.test')->first();

        $this->actingAs($plain)->patch(route('users.role', $resident), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('users.role', $resident), ['role' => 'admin'])->assertSessionHas('status');
        $this->assertTrue($resident->fresh()->isAdmin());
    }

    public function test_uye_silinince_davet_kodu_gecersiz_olur(): void
    {
        $member = Member::whereNull('user_id')->first();
        $this->actingAs($this->admin)->delete(route('site.members.destroy', [$this->apartment, $member]))->assertRedirect();
        $this->assertNull(Member::withTrashed()->find($member->id)->invite_code);
    }

    public function test_daire_etiketi_silme_onayindan_javascripte_tasamaz(): void
    {
        $this->apartment->units()->firstOrFail()->update(['unit_no' => "');alert(1)//"]);

        $html = $this->actingAs($this->admin)->get(route('site.units.index', $this->apartment))->assertOk()->getContent();

        preg_match('/onsubmit="(return confirm\([^"]*alert\(1\)[^"]*)"/', $html, $handler);
        $this->assertStringNotContainsString("');alert", html_entity_decode($handler[1], ENT_QUOTES));
    }

    public function test_tc_kimlik_ve_davet_kodu_yalnizca_yonetici_rolune_gorunur(): void
    {
        $member = Member::whereNull('user_id')->firstOrFail();
        $member->update(['id_number' => '12345678901']);
        $viewer = $this->admin();
        $this->apartment->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->get(route('site.members.show', [$this->apartment, $member]))->assertOk()
            ->assertDontSee('12345678901')->assertDontSee($member->invite_code);
        $this->actingAs($viewer)->get(route('site.members.index', $this->apartment))->assertOk()->assertDontSee($member->invite_code);

        $this->actingAs($this->admin)->get(route('site.members.show', [$this->apartment, $member]))->assertOk()
            ->assertSee('12345678901')->assertSee($member->invite_code);
    }

    public function test_tc_kimlik_no_veritabaninda_sifreli_tutulur_ve_eski_kayitlar_sifrelenir(): void
    {
        $member = Member::firstOrFail();
        DB::table('members')->update(['id_number' => null]);
        DB::table('members')->where('id', $member->id)->update(['id_number' => '10987654321']);

        (require database_path('migrations/2026_11_10_000500_encrypt_member_id_numbers.php'))->up();

        $this->assertNotSame('10987654321', DB::table('members')->where('id', $member->id)->value('id_number'));
        $this->assertSame('10987654321', $member->fresh()->id_number);
    }

    public function test_kullanici_detayi_bakanin_erisemedigi_siteyi_gostermez(): void
    {
        $viewer = $this->admin();
        $shared = $this->site(1, $viewer);
        $shared->update(['name' => 'Ortak Site']);
        $target = $this->admin();
        $shared->managers()->attach($target);
        $this->site(1, $target)->update(['name' => 'Gizli Site']);

        $this->actingAs($viewer)->get(route('users.show', $target))->assertOk()
            ->assertSee('Ortak Site')->assertDontSee('Gizli Site');
    }

    public function test_guvenlik_basliklari_eklenir_hsts_yalnizca_https_te_gonderilir(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $response->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_ayni_ipden_cok_hesap_denenince_giris_sinirlanir(): void
    {
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->post('/login', ['email' => "deneme{$attempt}@test.dev", 'password' => 'YanlisSifre1'])->assertRedirect();
        }

        $this->post('/login', ['email' => 'deneme21@test.dev', 'password' => 'YanlisSifre1'])->assertTooManyRequests();
    }
}
