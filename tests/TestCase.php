<?php

namespace Tests;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(bool $super = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin', 'is_super_admin' => $super])->save();

        return $user;
    }

    /** Bir yöneticisi, "A" bloğu, verilen sayıda dairesi ve bir nakit kasası olan site kurar. */
    protected function site(int $units = 2, ?User $manager = null): Apartment
    {
        $manager ??= $this->admin();
        $apartment = Apartment::create(['name' => 'Test Sitesi', 'code' => 'TST'.Apartment::count(), 'owner_id' => $manager->id]);
        $apartment->managers()->attach($manager);
        $block = $apartment->blocks()->create(['name' => 'A']);
        for ($i = 1; $i <= $units; $i++) {
            $apartment->units()->create(['block_id' => $block->id, 'unit_no' => (string) $i, 'gross_area' => 100]);
        }
        $apartment->cashboxes()->create(['name' => 'Merkez Kasa', 'type' => 'cash', 'balance' => 0]);

        return $apartment;
    }

    protected function unit(Apartment $apartment, int $index = 0): Unit
    {
        return $apartment->units()->orderBy('id')->get()[$index];
    }

    protected function cashbox(Apartment $apartment): Cashbox
    {
        return $apartment->cashboxes()->first();
    }

    /** Yevmiye her zaman dengede olmalı: toplam borç = toplam alacak */
    protected function assertLedgerBalanced(Apartment $apartment): void
    {
        $sums = $apartment->ledgerEntries()->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $this->assertEquals(round((float) $sums->d, 2), round((float) $sums->c, 2), 'Yevmiye dengede değil');
    }
}
