<?php

namespace Tests\Feature;

use App\Models\Apartment;
use App\Models\Member;
use App\Models\SiteNotification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $resident;

    private Apartment $site;

    private Unit $flat;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->manager = $this->admin();
        $this->site = $this->site(2, $this->manager);
        $this->flat = $this->unit($this->site);
        $this->resident = User::factory()->create()->fresh();
        $this->link($this->resident, $this->flat);
        $this->ticket = $this->site->tickets()->create(['subject' => 'Asansör', 'category' => 'Teknik', 'user_id' => $this->resident->id, 'unit_id' => $this->flat->id]);
    }

    private function link(User $user, Unit $unit): void
    {
        $this->site->members()->create(['unit_id' => $unit->id, 'user_id' => $user->id, 'full_name' => 'Sakin', 'invite_code' => Member::generateInviteCode('TST', (string) $unit->id)]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('ariza.jpg');
    }

    public function test_yonetim_yanitlar_sakine_bildirim_gider(): void
    {
        $this->actingAs($this->manager)->post(route('site.tickets.messages', [$this->site, $this->ticket]), ['body' => 'Teknisyen yolda'])->assertSessionHas('status');

        $message = TicketMessage::sole();
        $this->assertTrue($message->is_staff);
        $this->assertSame($this->manager->id, $message->user_id);
        $notification = SiteNotification::sole();
        $this->assertSame($this->flat->id, $notification->unit_id);
        $this->actingAs($this->resident)->get(route('portal.tickets', $this->site))->assertSee('Teknisyen yolda');
    }

    public function test_sakin_fotografla_yazar_yonetim_gorur(): void
    {
        $this->actingAs($this->resident)->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['body' => 'Hâlâ bozuk', 'photo' => $this->photo()])->assertSessionHas('status');

        $message = TicketMessage::sole();
        $this->assertFalse($message->is_staff);
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->assertStringStartsWith("tickets/{$this->site->id}/", $message->attachment_path);
        $this->assertSame(0, SiteNotification::count());

        $this->actingAs($this->manager)->get(route('site.tickets.index', $this->site))->assertSee('Hâlâ bozuk');
        $this->actingAs($this->manager)->get(route('site.tickets.attachment', [$this->site, $this->ticket, $message]))->assertOk();
        $this->actingAs($this->resident)->get(route('portal.tickets.attachment', [$this->site, $this->ticket->id, $message->id]))->assertOk();
    }

    public function test_yeni_talep_fotografla_acilir(): void
    {
        $this->actingAs($this->resident)->post(route('portal.tickets.store', $this->site), ['subject' => 'Su kaçağı', 'category' => 'Teknik', 'photo' => $this->photo()])->assertSessionHas('status');

        Storage::disk('local')->assertExists(TicketMessage::sole()->attachment_path);
    }

    public function test_bos_mesaj_ve_gecersiz_dosya_reddedilir(): void
    {
        $this->actingAs($this->resident);

        $this->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), [])->assertSessionHasErrors('body');
        $this->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['photo' => UploadedFile::fake()->create('zararli.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['photo' => UploadedFile::fake()->create('belge.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['photo' => UploadedFile::fake()->image('dev.jpg')->size(6000)])->assertSessionHasErrors('photo');

        $this->assertSame(0, TicketMessage::count());
    }

    public function test_kapanmis_talebe_yazilamaz(): void
    {
        $this->ticket->update(['status' => 'closed']);

        $this->actingAs($this->resident)->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['body' => 'x'])->assertSessionHas('error');

        $this->assertSame(0, TicketMessage::count());
    }

    public function test_cozulmus_talebe_sakin_yazinca_yeniden_acilir(): void
    {
        $this->ticket->update(['status' => 'resolved']);

        $this->actingAs($this->resident)->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['body' => 'Düzelmedi']);

        $this->assertSame('open', $this->ticket->fresh()->status);
    }

    public function test_baska_sakinin_talebine_ve_fotografina_erisilemez(): void
    {
        $message = $this->ticket->messages()->create(['apartment_id' => $this->site->id, 'attachment_path' => 'tickets/x.jpg', 'attachment_name' => 'x.jpg']);
        $neighbour = User::factory()->create()->fresh();
        $this->link($neighbour, $this->unit($this->site, 1));

        $this->actingAs($neighbour)->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['body' => 'x'])->assertNotFound();
        $this->actingAs($neighbour)->get(route('portal.tickets.attachment', [$this->site, $this->ticket->id, $message->id]))->assertNotFound();
    }

    public function test_baska_sitenin_yoneticisi_fotografi_goremez(): void
    {
        $message = $this->ticket->messages()->create(['apartment_id' => $this->site->id, 'attachment_path' => 'tickets/x.jpg', 'attachment_name' => 'x.jpg']);
        Storage::disk('local')->put('tickets/x.jpg', 'x');

        $this->actingAs($this->admin())->get(route('site.tickets.attachment', [$this->site, $this->ticket, $message]))->assertForbidden();
    }

    public function test_izleyici_yanit_yazamaz(): void
    {
        $viewer = $this->admin();
        $this->site->managers()->attach($viewer, ['role' => 'viewer']);

        $this->actingAs($viewer)->post(route('site.tickets.messages', [$this->site, $this->ticket]), ['body' => 'x'])->assertForbidden();
    }

    public function test_talep_silinince_fotograflari_da_silinir(): void
    {
        $this->actingAs($this->resident)->post(route('portal.tickets.messages', [$this->site, $this->ticket->id]), ['photo' => $this->photo()]);
        $path = TicketMessage::sole()->attachment_path;

        $this->actingAs($this->manager)->delete(route('site.tickets.destroy', [$this->site, $this->ticket]))->assertSessionHas('status');

        $this->assertNull($this->ticket->fresh());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_api_mesajlari_listeler_ve_mesaj_ekler(): void
    {
        $token = ['Authorization' => 'Bearer '.$this->resident->createToken('test')->plainTextToken];

        $this->postJson("/api/sites/{$this->site->id}/tickets/{$this->ticket->id}/messages", ['body' => 'Mobilden'], $token)->assertCreated();

        $this->getJson("/api/sites/{$this->site->id}/tickets", $token)->assertOk()->assertJsonPath('0.messages.0.body', 'Mobilden');
    }
}
