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

    /**
     * Bir tutarı ağırlıklara orantılı böler; toplam her zaman tam olarak $total kuruş eder
     * ("en büyük kalan" yöntemi). Ağırlıkların toplamı 0 ise eşit bölünür.
     *
     * @param  array<int|string, int|float>  $weights  anahtarlar korunur
     * @return array<int|string, int>
     */
    public static function allocate(int $total, array $weights): array
    {
        if ($weights === []) {
            return [];
        }
        $sum = array_sum($weights);
        if ($sum <= 0) {
            $weights = array_fill_keys(array_keys($weights), 1);
            $sum = count($weights);
        }
        $shares = $floors = [];
        foreach ($weights as $key => $weight) {
            $exact = $total * $weight / $sum;
            $floors[$key] = (int) floor($exact);
            $shares[$key] = $exact - $floors[$key];
        }
        $left = $total - array_sum($floors);
        arsort($shares);
        foreach (array_keys($shares) as $key) {
            if ($left-- <= 0) {
                break;
            }
            $floors[$key]++;
        }

        return array_replace($weights, $floors);
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
