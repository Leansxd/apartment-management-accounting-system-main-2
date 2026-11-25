<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Daire durumu (Dolu / Boş) ile sakin kayıtlarının uyumu */
class UnitOccupancyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->admin();
        $this->site = $this->site(1, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->flat->update(['status' => 'Boş']);
    }

    private function addMember(string $type, string $name): TestResponse
    {
        return $this->actingAs($this->manager)->post(route('site.members.store', $this->site), [
            'unit_id' => $this->flat->id, 'type' => $type, 'full_name' => $name,
        ]);
    }

    public function test_bos_daireye_kiraci_girince_daire_dolu_olur(): void
    {
        $this->addMember('tenant', 'Kaya Ltd.')->assertSessionHas('status', fn (string $m) => str_contains($m, 'Dolu'));

        $this->assertSame('Dolu', $this->flat->fresh()->status);
    }

    public function test_yalnizca_malik_eklenince_daire_bos_kalir(): void
    {
        $this->addMember('owner', 'Selin Avcı')->assertSessionHasNoErrors();

        $this->assertSame('Boş', $this->flat->fresh()->status);
    }

    public function test_daire_listesi_sakinin_malik_mi_kiraci_mi_oldugunu_gosterir(): void
    {
        $this->addMember('owner', 'Selin Avcı');
        $this->addMember('tenant', 'Kaya Ltd.');

        $this->actingAs($this->manager)->get(route('site.units.index', $this->site))
            ->assertSeeInOrder(['Selin Avcı', '(Mülk Sahibi)', ', ', 'Kaya Ltd.', '(Kiracı)']);
    }
}
