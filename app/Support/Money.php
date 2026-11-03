<?php

namespace App\Support;

/**
 * Para hesapları kuruş (tam sayı) üzerinden yapılır; kayan nokta yuvarlama hatası birikmez.
 * Veritabanında decimal(14,2) olarak saklanır.
 */
final class Money
{
    public static function cents(string|int|float|null $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** 1234.5 -> "1.234,50 ₺" */
    public static function format(string|int|float|null $value): string
    {
        return number_format((float) $value, 2, ',', '.').' ₺';
    }

    /** Formdan gelen "1.234,50" veya "1234.50" -> kuruş */
    public static function parse(string|int|float|null $input): int
    {
        $s = trim((string) $input);
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }

        return self::cents($s);
    }
}
