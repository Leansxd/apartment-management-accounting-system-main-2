<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_konutu_olmayan_sirket_sahibi_rehbere_konut_adimindan_baslar(): void
    {
        $this->actingAs($this->admin(super: true))->get('/')->assertOk()
            ->assertSee('data-setup-guide-start', false)
            ->assertSee('"key":"site:0"', false)
            ->assertSee('"locked":true', false)
            ->assertSee('"fill":"code"', false)
            ->assertSee('"typing":"code"', false)
            ->assertDontSee('zaten kurulu');
    }

    public function test_kurulu_apartmanda_once_soru_sorulur(): void
    {
        $owner = $this->admin(super: true);
        $apartment = $this->site(units: 2, manager: $owner);

        $this->actingAs($owner)->get(route('site.dashboard', $apartment))->assertOk()
            ->assertSee('zaten kurulu')
            ->assertSee('Tekrar kurmak istiyor musunuz');
    }

    public function test_daireleri_olmayan_apartmanda_soru_sorulmaz(): void
    {
        $owner = $this->admin(super: true);
        $apartment = $this->site(units: 0, manager: $owner);

        $this->actingAs($owner)->get(route('site.dashboard', $apartment))->assertOk()->assertDontSee('zaten kurulu');
    }

    public function test_rehberi_yalnizca_sirket_sahibi_gorur(): void
    {
        $manager = $this->admin();
        $apartment = $this->site(units: 0, manager: $manager);

        $this->actingAs($manager)->get(route('site.dashboard', $apartment))->assertOk()
            ->assertDontSee('setup-guide');
    }

    public function test_web_kurulum_sihirbazi_kapali(): void
    {
        $this->get('/setup')->assertNotFound();
        $this->get('/')->assertRedirect('/login');
    }
}
