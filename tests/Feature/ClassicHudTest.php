<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassicHudTest extends TestCase
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

    public function test_modul_sayfasi_klasik_menu_ile_kendi_icerigini_gosterir(): void
    {
        $this->actingAs($this->admin)->get(route('site.members.index', $this->apartment))
            ->assertOk()
            ->assertSee('id="classic-nav"', false)
            ->assertSee('id="settings-modal"', false)
            ->assertSee('data-pref="accent"', false)
            ->assertSee(route('site.settings', $this->apartment), false)
            ->assertDontSee('data-classic-dashboard', false);
    }

    public function test_pano_klasik_ve_modern_icerigi_birlikte_sunar(): void
    {
        foreach ([route('dashboard'), route('site.dashboard', $this->apartment)] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('data-classic-dashboard', false);
        }
    }

    public function test_klasik_arama_pano_yerine_sonuclari_gosterir(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard', ['q' => 'Demo']))
            ->assertOk()
            ->assertDontSee('data-classic-dashboard', false);
    }

    public function test_hizli_borclandirma_formu_borc_olusturur(): void
    {
        $unit = $this->apartment->units()->first();
        $before = Debt::count();

        $this->actingAs($this->admin)->get(route('site.dashboard', $this->apartment))
            ->assertSee('name="target" value="unit"', false);

        $this->actingAs($this->admin)->post(route('site.debts.store', $this->apartment), [
            'target' => 'unit',
            'unit_id' => $unit->id,
            'description' => 'Hızlı aidat',
            'amount' => '100.00',
            'due_date' => today()->endOfMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame($before + 1, Debt::count());
    }
}
