<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\Apartment;
use App\Models\Debt;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
