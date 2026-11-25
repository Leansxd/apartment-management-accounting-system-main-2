<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Member;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResidentPortalTest extends TestCase
{
    use RefreshDatabase;

    private Apartment $site;

    private Unit $flat;

    private Unit $neighbour;

    private User $resident;

    private Member $membership;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = $this->site(2);
        $this->flat = $this->unit($this->site);
        $this->neighbour = $this->unit($this->site, 1);
        $this->resident = User::factory()->create()->fresh();
        $this->membership = $this->invite($this->site, $this->flat, $this->resident);
    }

    private function invite(Apartment $site, Unit $unit, ?User $user = null): Member
    {
        return $site->members()->create([
            'unit_id' => $unit->id, 'user_id' => $user?->id, 'full_name' => 'Sakin', 'invite_code' => Member::generateInviteCode('TST', 'A1'),
        ]);
    }

    private function debt(Unit $unit, string $description, string $due = '2020-01-31'): void
    {
        app(FinanceService::class)->accrue($unit, ['description' => $description, 'amount' => 100000, 'due_date' => $due]);
    }

    /** @return array<string, array{0: array<int, string>|string}> */
    public static function portalPages(): array
    {
        return [
            'özet' => ['portal.overview'], 'borçlar' => ['portal.debts'], 'bildirimler' => ['portal.notifications'], 'anketler' => ['portal.polls'],
            'talepler' => ['portal.tickets'], 'rezervasyon' => ['portal.reservations'], 'toplantılar' => ['portal.meetings'],
        ];
    }

    #[DataProvider('portalPages')]
    public function test_sakin_sayfayi_acar(string $route): void
    {
        $this->debt($this->flat, 'Aidat');

        $this->actingAs($this->resident)->get(route($route, $this->site))->assertOk();
    }

    #[DataProvider('portalPages')]
    public function test_bagli_olmayan_kullanici_siteye_giremez(string $route): void
    {
        $this->actingAs(User::factory()->create())->get(route($route, $this->site))->assertForbidden();
    }

    #[DataProvider('portalPages')]
    public function test_misafir_girise_yonlendirilir(string $route): void
    {
        $this->get(route($route, $this->site))->assertRedirect('/login');
    }

    public function test_davet_koduyla_hesap_acilir_ve_daireye_baglanir(): void
    {
        $member = $this->invite($this->site, $this->neighbour);

        $this->post(route('portal.join.store'), [
            'name' => 'Yeni Sakin', 'email' => 'yeni@test.com', 'password' => 'guclu-sifre-1', 'password_confirmation' => 'guclu-sifre-1',
            'invite_code' => mb_strtolower($member->invite_code),
        ])->assertRedirect(route('portal.home'));

        $user = User::where('email', 'yeni@test.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isAdmin());
        $this->assertSame($user->id, $member->fresh()->user_id);
    }

    public function test_gecersiz_davet_koduyla_hesap_acilmaz(): void
    {
        $this->post(route('portal.join.store'), [
            'name' => 'X', 'email' => 'x@test.com', 'password' => 'guclu-sifre-1', 'password_confirmation' => 'guclu-sifre-1', 'invite_code' => 'YANLIS-KOD',
        ])->assertSessionHasErrors('invite_code');

        $this->assertDatabaseMissing('users', ['email' => 'x@test.com']);
        $this->assertGuest();
    }

    public function test_baskasinin_davet_kodu_kullanilamaz(): void
    {
        $this->post(route('portal.join.store'), [
            'name' => 'X', 'email' => 'x@test.com', 'password' => 'guclu-sifre-1', 'password_confirmation' => 'guclu-sifre-1',
            'invite_code' => $this->membership->invite_code,
        ])->assertSessionHasErrors('invite_code');

        $this->assertDatabaseMissing('users', ['email' => 'x@test.com']);
        $this->assertSame($this->resident->id, $this->membership->fresh()->user_id);
    }

    public function test_kayit_formundan_yonetici_rolu_verilemez(): void
    {
        $member = $this->invite($this->site, $this->neighbour);

        $this->post(route('portal.join.store'), [
            'name' => 'X', 'email' => 'x@test.com', 'password' => 'guclu-sifre-1', 'password_confirmation' => 'guclu-sifre-1',
            'invite_code' => $member->invite_code, 'role' => 'admin', 'is_super_admin' => 1,
        ]);

        $user = User::where('email', 'x@test.com')->firstOrFail();
        $this->assertFalse($user->isAdmin());
        $this->assertFalse((bool) $user->is_super_admin);
    }

    public function test_giris_yapmis_kullanici_davet_kodu_ekler(): void
    {
        $member = $this->invite($this->site, $this->neighbour);

        $this->actingAs($this->resident)->post(route('portal.redeem'), ['invite_code' => $member->invite_code])
            ->assertRedirect(route('portal.overview', $this->site));

        $this->assertSame($this->resident->id, $member->fresh()->user_id);
    }

    public function test_sakin_girisi_panele_yonlenir_yonetici_girisi_panoya(): void
    {
        $this->post('/login', ['email' => $this->resident->email, 'password' => 'password'])->assertRedirect(route('portal.home'));
        $this->post('/logout');

        $this->post('/login', ['email' => $this->admin()->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_tek_siteli_sakin_dogrudan_ozete_gider_baglantisiz_kod_formu_gorur(): void
    {
        $this->actingAs($this->resident)->get(route('portal.home'))->assertRedirect(route('portal.overview', $this->site));
        $this->actingAs(User::factory()->create())->get(route('portal.home'))->assertOk()->assertSee('Davet koduyla daire ekle');
    }

    public function test_sakin_yonetim_paneline_giremez(): void
    {
        $this->actingAs($this->resident)->get(route('site.collections.index', $this->site))->assertRedirect(route('login'));
    }

    public function test_sakin_yalnizca_kendi_borcunu_gorur(): void
    {
        $this->debt($this->flat, 'Benim aidatım');
        $this->debt($this->neighbour, 'Komşunun aidatı');
        $this->actingAs($this->resident);

        $this->get(route('portal.overview', $this->site))->assertSee('Benim aidatım')->assertDontSee('Komşunun aidatı');
        $this->get(route('portal.debts', $this->site))->assertSee('Benim aidatım')->assertDontSee('Komşunun aidatı');
    }

    public function test_baska_dairenin_ekstresi_acilamaz(): void
    {
        $this->actingAs($this->resident)->get(route('portal.debts', [$this->site, 'unit_id' => $this->neighbour->id]))->assertForbidden();
    }

    public function test_baglanti_kaldirilinca_erisim_biter(): void
    {
        $this->membership->delete();

        $this->actingAs($this->resident)->get(route('portal.overview', $this->site))->assertForbidden();
    }

    public function test_komsuya_ozel_bildirim_gorunmez_ve_okunamaz(): void
    {
        $this->site->siteNotifications()->create(['title' => 'Herkese', 'body' => 'x']);
        $private = $this->site->siteNotifications()->create(['title' => 'Komşuya', 'body' => 'x', 'unit_id' => $this->neighbour->id]);
        $this->actingAs($this->resident);

        $this->get(route('portal.notifications', $this->site))->assertSee('Herkese')->assertDontSee('Komşuya');
        $this->post(route('portal.notifications.read', [$this->site, $private->id]))->assertNotFound();
    }

    public function test_bildirim_okundu_isaretlenir(): void
    {
        $notification = $this->site->siteNotifications()->create(['title' => 'Herkese', 'body' => 'x']);

        $this->actingAs($this->resident)->post(route('portal.notifications.read', [$this->site, $notification->id]))->assertRedirect();

        $this->assertTrue($notification->readers()->whereKey($this->resident->id)->exists());
    }

    public function test_daire_basina_tek_oy_kullanilir(): void
    {
        $poll = $this->site->polls()->create(['question' => 'Soru?']);
        $option = $poll->options()->create(['text' => 'Evet']);
        $this->actingAs($this->resident);

        $this->post(route('portal.polls.vote', [$this->site, $poll->id]), ['option_id' => $option->id])->assertSessionHas('status');
        $this->post(route('portal.polls.vote', [$this->site, $poll->id]), ['option_id' => $option->id])->assertSessionHas('error');

        $this->assertSame(1, $poll->votes()->count());
    }

    public function test_kapali_ankete_ve_baska_anketin_secenegine_oy_verilemez(): void
    {
        $closed = $this->site->polls()->create(['question' => 'Kapalı?', 'is_active' => false]);
        $closedOption = $closed->options()->create(['text' => 'Evet']);
        $open = $this->site->polls()->create(['question' => 'Açık?']);
        $this->actingAs($this->resident);

        $this->post(route('portal.polls.vote', [$this->site, $closed->id]), ['option_id' => $closedOption->id])->assertSessionHas('error');
        $this->post(route('portal.polls.vote', [$this->site, $open->id]), ['option_id' => $closedOption->id])->assertSessionHasErrors('option_id');
        $this->assertSame(0, $closed->votes()->count());
    }

    public function test_talep_acilir_ve_sadece_sahibi_gorur(): void
    {
        $this->actingAs($this->resident)->post(route('portal.tickets.store', $this->site), ['subject' => 'Asansör bozuk', 'category' => 'Teknik'])->assertSessionHas('status');

        $ticket = Ticket::sole();
        $this->assertSame($this->resident->id, $ticket->user_id);
        $this->assertSame($this->flat->id, $ticket->unit_id);
        $this->get(route('portal.tickets', $this->site))->assertSee('Asansör bozuk');

        $other = User::factory()->create();
        $this->invite($this->site, $this->neighbour, $other);
        $this->actingAs($other)->get(route('portal.tickets', $this->site))->assertDontSee('Asansör bozuk');
    }

    public function test_baska_daire_adina_talep_acilamaz(): void
    {
        $this->actingAs($this->resident)->post(route('portal.tickets.store', $this->site), ['subject' => 'X', 'category' => 'Genel', 'unit_id' => $this->neighbour->id])
            ->assertSessionHasErrors('unit_id');

        $this->assertSame(0, Ticket::count());
    }

    public function test_rezervasyon_yapilir_ve_iptal_edilir(): void
    {
        $area = $this->site->commonAreas()->create(['name' => 'Salon', 'requires_approval' => false]);
        $date = today()->addDays(3)->toDateString();
        $this->actingAs($this->resident);

        $this->post(route('portal.reservations.store', $this->site), ['common_area_id' => $area->id, 'date' => $date, 'start_time' => '10:00', 'end_time' => '11:00'])
            ->assertSessionHas('status');
        $reservation = Reservation::sole();
        $this->assertSame($this->flat->id, $reservation->unit_id);

        $this->delete(route('portal.reservations.cancel', [$this->site, $reservation->id]))->assertSessionHas('status');
        $this->assertSame('cancelled', $reservation->fresh()->status);
    }

    public function test_komsunun_rezervasyonu_iptal_edilemez(): void
    {
        $area = $this->site->commonAreas()->create(['name' => 'Salon', 'requires_approval' => false]);
        $reservation = $this->site->reservations()->create([
            'common_area_id' => $area->id, 'unit_id' => $this->neighbour->id, 'date' => today()->addDays(3), 'start_time' => '10:00', 'end_time' => '11:00', 'status' => 'approved',
        ]);

        $this->actingAs($this->resident)->delete(route('portal.reservations.cancel', [$this->site, $reservation->id]))->assertNotFound();

        $this->assertSame('approved', $reservation->fresh()->status);
    }

    public function test_sakin_sifresini_degistirebilir(): void
    {
        $this->actingAs($this->resident)->put(route('portal.password'), [
            'current_password' => 'password', 'password' => 'yeni-sifre-123', 'password_confirmation' => 'yeni-sifre-123',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['email' => $this->resident->email, 'password' => 'yeni-sifre-123'])->assertRedirect(route('portal.home'));
    }
}
