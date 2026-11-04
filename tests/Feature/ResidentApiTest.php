<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_kayit_giris_ve_davet_koduyla_baglanma(): void
    {
        $apt = $this->site(2);
        $unit = $this->unit($apt);
        $member = $apt->members()->create(['unit_id' => $unit->id, 'full_name' => 'Sakin', 'invite_code' => Member::generateInviteCode('TST', 'A1')]);

        $token = $this->postJson('/api/register', ['name' => 'Sakin', 'email' => 's@test.com', 'password' => 'password123', 'device_name' => 'ios'])
            ->assertCreated()->json('token');
        $headers = ['Authorization' => "Bearer {$token}"];
        $this->assertFalse(User::where('email', 's@test.com')->first()->isAdmin()); // mobil kayıt asla admin olmaz

        $this->getJson("/api/sites/{$apt->id}/debts", $headers)->assertForbidden(); // henüz bağlı değil

        $this->postJson('/api/redeem-invite', ['invite_code' => strtolower($member->invite_code)], $headers)
            ->assertOk()->assertJson(['unit_id' => $unit->id]);
        $this->getJson('/api/me', $headers)->assertJsonPath('residencies.0.unit_id', $unit->id);
        $this->getJson("/api/sites/{$apt->id}/debts", $headers)->assertOk();

        // Kod başka hesapta kullanılamaz
        $other = User::factory()->create();
        $this->app['auth']->forgetGuards(); // testte önceki isteğin kullanıcısı önbellekte kalmasın
        $this->postJson('/api/redeem-invite', ['invite_code' => $member->invite_code], $this->token($other))->assertStatus(409);
    }

    public function test_sakin_yalnizca_kendi_dairesinin_borcunu_ve_bildirimini_gorur(): void
    {
        $apt = $this->site(2);
        [$mine, $theirs] = [$this->unit($apt, 0), $this->unit($apt, 1)];
        $user = User::factory()->create();
        $apt->members()->create(['unit_id' => $mine->id, 'full_name' => 'Ben', 'user_id' => $user->id]);
        $finance = app(\App\Services\FinanceService::class);
        $finance->accrue($mine, ['description' => 'Benim', 'amount' => 1000, 'due_date' => '2026-01-31']);
        $finance->accrue($theirs, ['description' => 'Komşu', 'amount' => 1000, 'due_date' => '2026-01-31']);
        $apt->siteNotifications()->create(['title' => 'Herkese', 'body' => 'x']);
        $apt->siteNotifications()->create(['title' => 'Komşuya', 'body' => 'x', 'unit_id' => $theirs->id]);

        $headers = $this->token($user);
        $this->getJson("/api/sites/{$apt->id}/debts", $headers)->assertJsonCount(1)->assertJsonPath('0.description', 'Benim');
        $notifications = $this->getJson("/api/sites/{$apt->id}/notifications", $headers)->assertJsonCount(1);
        $this->postJson("/api/sites/{$apt->id}/notifications/{$notifications->json('0.id')}/read", [], $headers)->assertOk();
        $this->getJson("/api/sites/{$apt->id}/notifications", $headers)->assertJsonPath('0.is_read', true);

        $foreign = $apt->siteNotifications()->where('title', 'Komşuya')->first();
        $this->postJson("/api/sites/{$apt->id}/notifications/{$foreign->id}/read", [], $headers)->assertNotFound();
    }

    public function test_uyelik_silinince_erisim_hemen_biter(): void
    {
        $apt = $this->site(1);
        $user = User::factory()->create();
        $member = $apt->members()->create(['unit_id' => $this->unit($apt)->id, 'full_name' => 'Ben', 'user_id' => $user->id]);
        $headers = $this->token($user);

        $this->getJson("/api/sites/{$apt->id}/debts", $headers)->assertOk();
        $member->delete();
        $this->getJson("/api/sites/{$apt->id}/debts", $headers)->assertForbidden();
    }

    public function test_davet_kodu_tahmini_sunucuda_sinirlanir(): void
    {
        $headers = $this->token(User::factory()->create());
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/redeem-invite', ['invite_code' => "YANLIS-KOD-{$i}"], $headers)->assertNotFound();
        }
        $this->postJson('/api/redeem-invite', ['invite_code' => 'YANLIS-KOD-X'], $headers)->assertStatus(429);
    }

    public function test_anket_daire_basina_tek_oy(): void
    {
        $apt = $this->site(1);
        $user = User::factory()->create();
        $apt->members()->create(['unit_id' => $this->unit($apt)->id, 'full_name' => 'Ben', 'user_id' => $user->id]);
        $poll = $apt->polls()->create(['question' => 'Soru?']);
        $option = $poll->options()->create(['text' => 'Evet']);
        $headers = $this->token($user);

        $this->postJson("/api/sites/{$apt->id}/polls/{$poll->id}/vote", ['option_id' => $option->id], $headers)->assertCreated();
        $this->postJson("/api/sites/{$apt->id}/polls/{$poll->id}/vote", ['option_id' => $option->id], $headers)->assertStatus(409);
        $this->getJson("/api/sites/{$apt->id}/polls", $headers)->assertJsonPath('0.options.0.votes', 1);
    }

    public function test_mobil_rezervasyon_alan_listesi_cakisma_ve_iptal(): void
    {
        $apt = $this->site(2);
        [$mine, $theirs] = $apt->units()->orderBy('id')->get();
        $user = User::factory()->create();
        $apt->members()->create(['unit_id' => $mine->id, 'full_name' => 'Ben', 'user_id' => $user->id]);
        $area = $apt->commonAreas()->create(['name' => 'Salon', 'hourly_fee' => 100, 'requires_approval' => false]);
        $apt->commonAreas()->create(['name' => 'Kapalı', 'is_active' => false]);
        $headers = $this->token($user);
        $date = now()->addDays(3)->toDateString();

        $this->getJson("/api/sites/{$apt->id}/areas", $headers)->assertJsonCount(1)->assertJsonPath('0.name', 'Salon');
        $id = $this->postJson("/api/sites/{$apt->id}/reservations", ['common_area_id' => $area->id, 'date' => $date, 'start_time' => '10:00', 'end_time' => '12:00'], $headers)
            ->assertCreated()->assertJsonPath('status', 'approved')->assertJsonPath('fee', 200)->json('id');

        // Aynı saat ikinci kez alınamaz; başkasının dairesi adına rezervasyon yapılamaz
        $this->postJson("/api/sites/{$apt->id}/reservations", ['common_area_id' => $area->id, 'date' => $date, 'start_time' => '11:00', 'end_time' => '13:00'], $headers)->assertStatus(422);
        $this->postJson("/api/sites/{$apt->id}/reservations", ['common_area_id' => $area->id, 'unit_id' => $theirs->id, 'date' => $date, 'start_time' => '15:00', 'end_time' => '16:00'], $headers)->assertStatus(422);

        $this->getJson("/api/sites/{$apt->id}/reservations", $headers)->assertJsonCount(1)->assertJsonPath('0.can_cancel', true);
        $this->deleteJson("/api/sites/{$apt->id}/reservations/{$id}", [], $headers)->assertOk();
        $this->assertSame(0, \App\Models\Debt::count()); // ücret borcu silindi

        $apt->meetings()->create(['title' => 'Genel kurul', 'meets_at' => now()->addWeek()]);
        $this->getJson("/api/sites/{$apt->id}/meetings", $headers)->assertJsonPath('0.title', 'Genel kurul');
    }
}
