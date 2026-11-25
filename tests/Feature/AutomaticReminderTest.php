<?php

namespace Tests\Feature;

use App\Mail\SiteMessageMail;
use App\Models\Apartment;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\SiteNotification;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomaticReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Apartment $site;

    private Unit $flat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-03-15 09:00');
        $this->manager = $this->admin();
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
    }

    private function overdueDebt(): void
    {
        app(FinanceService::class)->accrue($this->flat, ['description' => 'Aidat', 'amount' => 150000, 'due_date' => '2026-02-28']);
    }

    private function enable(array $attributes = []): void
    {
        $this->site->update($attributes + ['reminder_auto_run' => true, 'reminder_interval_days' => 7]);
    }

    public function test_acik_sitede_gecikmis_daireye_tutariyla_hatirlatma_gider(): void
    {
        $this->overdueDebt();
        $this->enable();

        $this->artisan('app:send-reminders')->assertSuccessful();

        $notification = SiteNotification::sole();
        $this->assertSame($this->flat->id, $notification->unit_id);
        $this->assertStringContainsString('1.500,00', $notification->body);
        $this->assertNotNull($this->site->fresh()->reminder_last_run_at);
        $this->assertSame(1, AuditLog::where('action', 'AUTO_REMINDERS')->firstOrFail()->data['units']);
    }

    public function test_aralik_dolmadan_tekrar_gonderilmez_dolunca_gonderilir(): void
    {
        $this->overdueDebt();
        $this->enable();
        $this->artisan('app:send-reminders');

        $this->travelTo('2026-03-21 09:00');
        $this->artisan('app:send-reminders');
        $this->assertSame(1, SiteNotification::count());

        $this->travelTo('2026-03-22 09:00');
        $this->artisan('app:send-reminders');
        $this->assertSame(2, SiteNotification::count());
    }

    public function test_kapali_sitede_hatirlatma_gitmez(): void
    {
        $this->overdueDebt();

        $this->artisan('app:send-reminders')->assertSuccessful();

        $this->assertSame(0, SiteNotification::count());
    }

    public function test_gecikmis_borc_yoksa_hatirlatma_gitmez_ve_sure_baslamaz(): void
    {
        $this->enable();

        $this->artisan('app:send-reminders')->assertSuccessful();

        $this->assertSame(0, SiteNotification::count());
        $this->assertNull($this->site->fresh()->reminder_last_run_at);
    }

    public function test_eposta_ayari_acikken_uyeye_eposta_kuyruga_alinir(): void
    {
        Mail::fake();
        $this->overdueDebt();
        $this->enable(['reminder_send_email' => true]);
        $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => 'Sakin', 'email' => 'sakin@test.com', 'invite_code' => Member::generateInviteCode('TST', 'A1')]);

        $this->artisan('app:send-reminders');

        Mail::assertQueued(SiteMessageMail::class, fn (SiteMessageMail $mail) => $mail->hasTo('sakin@test.com'));
    }

    public function test_eposta_ayari_kapaliyken_eposta_gitmez(): void
    {
        Mail::fake();
        $this->overdueDebt();
        $this->enable();
        $this->site->members()->create(['unit_id' => $this->flat->id, 'full_name' => 'Sakin', 'email' => 'sakin@test.com', 'invite_code' => Member::generateInviteCode('TST', 'A1')]);

        $this->artisan('app:send-reminders');

        Mail::assertNothingQueued();
    }

    public function test_hatirlatma_her_sabah_zamanlanir(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'app:send-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('0 9 * * *', $event->expression);
    }

    public function test_yonetici_ayarlari_kaydeder(): void
    {
        $this->actingAs($this->manager)->put(route('site.reminders.settings', $this->site), [
            'reminder_auto_run' => '1', 'reminder_interval_days' => 14,
        ])->assertSessionHas('status');

        $site = $this->site->fresh();
        $this->assertTrue($site->reminder_auto_run);
        $this->assertFalse($site->reminder_send_email);
        $this->assertSame(14, $site->reminder_interval_days);
    }

    public function test_gecersiz_aralik_reddedilir(): void
    {
        $this->actingAs($this->manager)->put(route('site.reminders.settings', $this->site), ['reminder_interval_days' => 0])
            ->assertSessionHasErrors('reminder_interval_days');
    }

    public function test_izleyici_ayarlari_degistiremez(): void
    {
        $viewer = $this->admin();
        $this->site->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->put(route('site.reminders.settings', $this->site), ['reminder_interval_days' => 3])->assertForbidden();
    }

    public function test_elle_hatirlatma_calismaya_devam_eder_ve_kaydedilir(): void
    {
        $this->overdueDebt();

        $this->actingAs($this->manager)->post(route('site.reminders.send', $this->site), ['unit_ids' => [$this->flat->id]])->assertSessionHas('status');

        $this->assertSame($this->manager->id, SiteNotification::sole()->created_by);
        $this->assertSame($this->manager->id, AuditLog::where('action', 'SEND_REMINDERS')->firstOrFail()->user_id);
    }
}
