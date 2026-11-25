<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Member;
use App\Models\Poll;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Sakin işlemleri: mobil API ve web sakin paneli aynı kuralları kullanır. İhlalde hata kodu HTTP durumudur. */
class ResidentService
{
    public function redeemInvite(string $code, User $user): Member
    {
        $code = mb_strtoupper(trim($code));

        return DB::transaction(function () use ($code, $user) {
            $member = Member::where('invite_code', $code)->lockForUpdate()->first();
            if (! $member) {
                throw new FinanceException('Davet kodu bulunamadı.', 404);
            }
            if ($member->user_id && $member->user_id !== $user->id) {
                throw new FinanceException('Bu davet kodu başka bir hesap tarafından kullanılmış.', 409);
            }
            $member->update(['user_id' => $user->id, 'activated_at' => $member->activated_at ?? now()]);

            return $member->load('unit.block', 'apartment');
        });
    }

    /** Daire başına tek oy */
    public function castVote(Poll $poll, int $optionId, int $unitId, User $user): void
    {
        if (! $poll->isOpen()) {
            throw new FinanceException('Anket oylamaya kapalı.', 422);
        }

        try {
            $poll->votes()->create(['poll_option_id' => $optionId, 'unit_id' => $unitId, 'user_id' => $user->id]);
        } catch (UniqueConstraintViolationException) {
            throw new FinanceException('Bu daire adına zaten oy kullanıldı.', 409);
        }
    }

    /** @param array{subject: string, description?: string|null, category: string, priority?: string|null} $data */
    public function openTicket(Apartment $apartment, Unit $unit, User $user, array $data): Ticket
    {
        return $apartment->tickets()->create([
            'unit_id' => $unit->id, 'unit_no' => $unit->label(), 'user_id' => $user->id,
            'subject' => $data['subject'], 'description' => $data['description'] ?? null,
            'category' => $data['category'], 'priority' => $data['priority'] ?? 'normal',
        ]);
    }
}
