<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use App\Models\Debt;
use App\Models\Member;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceException;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Borç sorumlusu (malik / kiracı), tahsilatta ödeyen kişi ve borçların elle seçilmesi */
class DebtResponsibilityTest extends TestCase
{
    use RefreshDatabase;

    private FinanceService $finance;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    private Member $owner;

    private Member $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = app(FinanceService::class);
        $this->manager = $this->admin();
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->owner = $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => 'Selin Avcı', 'type' => 'owner']);
        $this->tenant = $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => 'Kaya Ltd.', 'type' => 'tenant']);
    }

    private function debt(string $description, string $dueDate, int $cents, ?string $responsible = null): Debt
    {
        $this->finance->accrue($this->flat, ['description' => $description, 'amount' => $cents, 'due_date' => $dueDate, 'responsible' => $responsible]);

        return Debt::where('description', $description)->sole();
    }

    /** @return array{cashbox: Cashbox, amount: int, method: string} */
    private function part(int $cents): array
    {
        return ['cashbox' => $this->cashbox($this->site), 'amount' => $cents, 'method' => 'collection'];
    }

    public function test_borc_formundan_sorumlu_kaydedilir(): void
    {
        $this->actingAs($this->manager)->post(route('site.debts.store', $this->site), [
            'target' => 'unit', 'unit_id' => $this->flat->id, 'description' => 'Asansör yenileme',
            'amount' => 1500, 'due_date' => '2026-12-31', 'responsible' => 'owner',
        ])->assertSessionHasNoErrors();

        $this->assertSame('owner', Debt::where('description', 'Asansör yenileme')->sole()->responsible);
    }

    public function test_aidat_tanimindaki_sorumlu_borca_ve_taksitlerine_gecer(): void
    {
        $this->site->duesDefinitions()->create(['name' => 'İşletme', 'amount' => 400, 'calculation' => 'fixed',
            'responsible' => 'tenant', 'target_type' => 'unit', 'target_id' => $this->flat->id, 'due_day' => 15, 'is_active' => true]);

        $this->finance->generateDues($this->site, '2026-12');
        $debt = Debt::where('unit_id', $this->flat->id)->sole();
        $this->finance->installments($debt, 2, '2027-01-15');

        $this->assertSame('tenant', $debt->responsible);
        $this->assertSame(['tenant', 'tenant'], Debt::where('kind', 'installment')->pluck('responsible')->all());
    }

    public function test_kiraci_odeyince_malik_borcu_otomatik_kapanmaz(): void
    {
        $ownerDebt = $this->debt('Çatı onarımı', '2026-01-31', 10000, 'owner');
        $tenantDebt = $this->debt('Aidat', '2026-02-28', 5000, 'tenant');

        $tx = $this->finance->collectParts($this->flat, [$this->part(8000)], payer: $this->tenant)->first();

        $this->assertSame(Debt::PENDING, $ownerDebt->fresh()->status);
        $this->assertSame(Debt::PAID, $tenantDebt->fresh()->status);
        $this->assertEquals(30.00, (float) $tx->to_advance);
        $this->assertSame($this->tenant->id, $tx->payer_member_id);
    }

    public function test_odeyen_secilmezse_tum_borclar_en_eski_vadeden_kapanir(): void
    {
        $ownerDebt = $this->debt('Çatı onarımı', '2026-01-31', 10000, 'owner');

        $this->finance->collectParts($this->flat, [$this->part(10000)]);

        $this->assertSame(Debt::PAID, $ownerDebt->fresh()->status);
    }

    public function test_secilen_vadenin_borcu_eski_borclardan_once_kapanir(): void
    {
        $january = $this->debt('Ocak aidatı', '2026-01-31', 5000);
        $march = $this->debt('Mart aidatı', '2026-03-31', 5000);

        $this->finance->collectParts($this->flat, [$this->part(5000)], allocations: [$march->id => 5000]);

        $this->assertSame(Debt::PAID, $march->fresh()->status);
        $this->assertSame(Debt::PENDING, $january->fresh()->status);
    }

    public function test_secilen_tutarlar_parcalar_arasinda_dagitilir_artan_otomatik_gider(): void
    {
        $january = $this->debt('Ocak aidatı', '2026-01-31', 5000);
        $march = $this->debt('Mart aidatı', '2026-03-31', 5000);

        $this->finance->collectParts($this->flat, [$this->part(3000), $this->part(4000)], allocations: [$march->id => 5000]);

        $this->assertSame(Debt::PAID, $march->fresh()->status);
        $this->assertEquals(20.00, (float) $january->fresh()->paid_amount);
    }

    public function test_secilen_tutar_odemeden_fazlaysa_hicbir_sey_kaydedilmez(): void
    {
        $march = $this->debt('Mart aidatı', '2026-03-31', 5000);

        $this->expectException(FinanceException::class);
        try {
            $this->finance->collectParts($this->flat, [$this->part(3000)], allocations: [$march->id => 5000]);
        } finally {
            $this->assertSame(0, CashTransaction::count());
            $this->assertEquals(0, (float) $march->fresh()->paid_amount);
        }
    }

    public function test_secilen_tutar_borcun_kalanini_asamaz(): void
    {
        $march = $this->debt('Mart aidatı', '2026-03-31', 5000);

        $this->expectExceptionMessage('kalan borcu');
        $this->finance->collectParts($this->flat, [$this->part(9000)], allocations: [$march->id => 6000]);
    }

    public function test_baska_dairenin_borcu_secilemez(): void
    {
        $this->finance->accrue($this->unit($this->site, 1), ['description' => 'Komşu borcu', 'amount' => 5000, 'due_date' => '2026-01-31']);
        $foreign = Debt::where('description', 'Komşu borcu')->sole();

        $this->expectExceptionMessage('açık borçları arasında değil');
        $this->finance->collectParts($this->flat, [$this->part(5000)], allocations: [$foreign->id => 5000]);
    }

    public function test_formdan_odeyen_ve_secilen_borc_kaydedilir_makbuzda_odeyen_gorunur(): void
    {
        $january = $this->debt('Ocak aidatı', '2026-01-31', 5000);
        $march = $this->debt('Mart aidatı', '2026-03-31', 5000);

        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id, 'payer_member_id' => $this->owner->id,
            'parts' => [['cashbox_id' => $this->cashbox($this->site)->id, 'amount' => 50, 'method' => 'collection']],
            'allocations' => [$march->id => '50', $january->id => ''],
        ])->assertSessionHasNoErrors();

        $tx = CashTransaction::sole();
        $this->assertSame($this->owner->id, $tx->payer_member_id);
        $this->assertSame(Debt::PAID, $march->fresh()->status);
        $this->actingAs($this->manager)->get(route('site.transactions.receipt', [$this->site, $tx]))
            ->assertSee('Selin Avcı')->assertDontSee('Kaya Ltd.');
    }

    public function test_baska_dairenin_sakini_odeyen_secilemez(): void
    {
        $neighbour = $this->site->members()->create(['unit_id' => $this->unit($this->site, 1)->id, 'full_name' => 'Komşu', 'type' => 'owner']);

        $this->actingAs($this->manager)->post(route('site.collections.store', $this->site), [
            'unit_id' => $this->flat->id, 'payer_member_id' => $neighbour->id,
            'parts' => [['cashbox_id' => $this->cashbox($this->site)->id, 'amount' => 50, 'method' => 'collection']],
        ])->assertSessionHasErrors('payer_member_id');

        $this->assertSame(0, CashTransaction::count());
    }

    public function test_secilerek_odenen_tahsilat_iptal_edilince_borc_geri_acilir(): void
    {
        $march = $this->debt('Mart aidatı', '2026-03-31', 5000);
        $tx = $this->finance->collectParts($this->flat, [$this->part(5000)], allocations: [$march->id => 5000])->first();

        $this->finance->reverse($tx, 'Yanlış daire');

        $this->assertSame(Debt::PENDING, $march->fresh()->status);
        $this->assertLedgerBalanced($this->site);
    }
}
