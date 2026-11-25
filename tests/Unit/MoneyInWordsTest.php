<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** Makbuzlardaki "Yalnız: …" satırı için tutarın yazıyla okunuşu */
class MoneyInWordsTest extends TestCase
{
    #[TestWith([0, 'sıfır Türk lirası'])]
    #[TestWith([1, 'bir Türk lirası'])]
    #[TestWith([10.05, 'on Türk lirası beş kuruş'])]
    #[TestWith([100, 'yüz Türk lirası'])]
    #[TestWith([1000, 'bin Türk lirası'])]
    #[TestWith([1001, 'bin bir Türk lirası'])]
    #[TestWith([1250.5, 'bin iki yüz elli Türk lirası elli kuruş'])]
    #[TestWith([21000, 'yirmi bir bin Türk lirası'])]
    #[TestWith([1100000.99, 'bir milyon yüz bin Türk lirası doksan dokuz kuruş'])]
    #[TestWith(['2700.00', 'iki bin yedi yüz Türk lirası'])]
    public function test_tutar_yaziyla_okunur(int|float|string $amount, string $expected): void
    {
        $this->assertSame($expected, Money::inWords($amount));
    }
}
