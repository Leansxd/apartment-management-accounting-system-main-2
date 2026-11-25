<?php

namespace App\Support;

/**
 * Para hesapları kuruş (tam sayı) üzerinden yapılır; kayan nokta yuvarlama hatası birikmez.
 * Veritabanında decimal(14,2) olarak saklanır.
 */
final class Money
{
    private const SYMBOLS = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];

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

    /** 1234.5, "USD" -> "1.234,50 $" */
    public static function formatIn(string|int|float|null $value, string $currency): string
    {
        return number_format((float) $value, 2, ',', '.').' '.(self::SYMBOLS[$currency] ?? $currency);
    }

    /** "30.500000" -> "30,5000" */
    public static function formatRate(string|int|float|null $rate): string
    {
        return number_format((float) $rate, 4, ',', '.');
    }

    /** Kuruş cinsinden döviz tutarını verilen kurla TRY kuruşuna çevirir */
    public static function convert(int $cents, string|float $rate): int
    {
        return (int) round($cents * (float) $rate);
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

    /**
     * Banka ekstresindeki tutarı okur (kuruş). Türkçe (1.250,00 · 1.250 · 12.500) ve İngilizce (1,234.56) biçimleri,
     * para birimi işaretleri (TL, ₺), boşluklu binlik ayracı ve parantezli / sonda eksi işaretli negatifleri tanır.
     * İki ayraç birlikteyse sondaki ondalık ayracıdır; yalnız nokta varsa ve ardından tam 3 hane geliyorsa binlik sayılır.
     * Form alanlarında kullanılmaz: orada "12.500" gerçekten 12,5'tir (parse kullanılır).
     */
    public static function parseStatementAmount(string|int|float|null $input): int
    {
        $raw = trim((string) $input);
        $negative = (bool) preg_match('/^\(.*\)$|^-|-$/u', preg_replace('/[^\d.,()\-]/u', '', $raw));
        $s = preg_replace('/[^\d.,]/u', '', $raw);
        if ($s === '' || ! preg_match('/\d/', $s)) {
            return 0;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            [$decimal, $thousands] = $lastComma > $lastDot ? [',', '.'] : ['.', ','];
            $s = str_replace([$thousands, $decimal], ['', '.'], $s);
        } elseif ($lastComma !== false) {
            $s = substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($lastDot !== false && (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s))) {
            $s = str_replace('.', '', $s);
        }

        $cents = self::cents($s);

        return $negative ? -$cents : $cents;
    }

    /** Tutarın yazıyla Türkçe okunuşu: 1250.5 → "bin iki yüz elli Türk lirası elli kuruş" (makbuz ve resmi belgeler için) */
    public static function inWords(string|int|float|null $amount): string
    {
        $cents = abs(self::cents($amount));
        $lira = intdiv($cents, 100);
        $kurus = $cents % 100;

        return trim((self::numberInWords($lira) ?: 'sıfır').' Türk lirası'.($kurus ? ' '.self::numberInWords($kurus).' kuruş' : ''));
    }

    private static function numberInWords(int $number): string
    {
        $ones = ['', 'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz'];
        $tens = ['', 'on', 'yirmi', 'otuz', 'kırk', 'elli', 'altmış', 'yetmiş', 'seksen', 'doksan'];
        $scales = ['', 'bin', 'milyon', 'milyar', 'trilyon'];
        $parts = [];
        for ($scale = 0; $number > 0; $scale++, $number = intdiv($number, 1000)) {
            $chunk = $number % 1000;
            if ($chunk === 0) {
                continue;
            }
            $hundreds = intdiv($chunk, 100);
            $words = array_filter([
                $hundreds ? trim(($hundreds > 1 ? $ones[$hundreds] : '').' yüz') : '',
                $tens[intdiv($chunk % 100, 10)],
                $ones[$chunk % 10],
            ]);
            // "bir bin" denmez: 1.000 "bin"dir
            $text = $scale === 1 && $chunk === 1 ? 'bin' : trim(implode(' ', $words).' '.$scales[$scale]);
            array_unshift($parts, $text);
        }

        return implode(' ', $parts);
    }
}
