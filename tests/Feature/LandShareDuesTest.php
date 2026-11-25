<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Debt;
use App\Models\DuesDefinition;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Arsa payına göre aidat: tanımdaki toplam tutar dairelere arsa payı oranında bölünür */
class LandShareDuesTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->admin();
        $this->site = $this->site(3, $this->manager);
    }

    /** @param  list<float|null>  $shares */
    private function shares(array $shares): void
    {
        foreach ($shares as $index => $share) {
            $this->unit($this->site, $index)->update(['land_share' => $share]);
        }
    }

    private function definition(float $total): DuesDefinition
    {
        return $this->site->duesDefinitions()->create(['name' => 'Yakıt', 'amount' => $total, 'calculation' => 'land_share',
            'target_type' => 'all', 'due_day' => 31, 'is_active' => true]);
    }

    /** @return list<float> daire sırasıyla borç tutarları */
    private function amounts(): array
    {
        return $this->site->units()->orderBy('id')->get()
            ->map(fn ($unit) => (float) (Debt::where('unit_id', $unit->id)->value('amount') ?? 0))->all();
    }

    public function test_toplam_tutar_arsa_payi_oraninda_bolunur(): void
    {
        $this->shares([0.2, 0.3, 0.5]);
        $this->definition(1000);

        app(FinanceService::class)->generateDues($this->site, '2026-12');

        $this->assertSame([200.0, 300.0, 500.0], $this->amounts());
    }

    public function test_kurus_kaybi_olmadan_bolunur(): void
    {
        $this->shares([1, 1, 1]);
        $this->definition(100);

        app(FinanceService::class)->generateDues($this->site, '2026-12');

        $this->assertEqualsWithDelta(100.0, array_sum($this->amounts()), 0.001);
        $this->assertEqualsCanonicalizing([33.34, 33.33, 33.33], $this->amounts());
    }

    public function test_arsa_payi_olmayan_daire_atlanir_ve_raporlanir(): void
    {
        $this->shares([0.4, 0.6, null]);
        $this->definition(1000);

        $report = app(FinanceService::class)->generateDues($this->site, '2026-12');

        $this->assertSame([400.0, 600.0, 0.0], $this->amounts());
        $this->assertSame([$this->unit($this->site, 2)->label()], $report['skipped_no_area']);
    }

    public function test_tanim_formdan_arsa_payi_hesabiyla_kaydedilir(): void
    {
        $this->actingAs($this->manager)->post(route('site.dues.store', $this->site), [
            'name' => 'Yakıt', 'amount' => 1000, 'calculation' => 'land_share', 'target_type' => 'all', 'due_day' => 31,
        ])->assertSessionHasNoErrors();

        $this->assertSame('land_share', DuesDefinition::sole()->calculation);
    }
}
