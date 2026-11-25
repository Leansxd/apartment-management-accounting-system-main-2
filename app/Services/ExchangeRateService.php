<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Cashbox;
use App\Models\CashTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Tahsilat ekranındaki kur önerileri. Öneri TCMB'nin günlük döviz alış kurudur; TCMB'ye ulaşılamazsa
 * sitede o dövizle kullanılan son kur önerilir. Öneri yalnızca varsayılandır, kur her zaman değiştirilebilir.
 */
class ExchangeRateService
{
    private const URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    private const CACHE_KEY = 'exchange_rates.tcmb';

    private const CACHE_SECONDS = 3600;

    /** Başarısız sorgu kısa süre saklanır: TCMB yanıt vermezken her sayfa açılışı beklemesin. */
    private const FAILURE_CACHE_SECONDS = 600;

    /**
     * Sitenin döviz kasalarındaki para birimleri için öneriler.
     *
     * @return array<string, array{rate: string, source: string}>
     */
    public function suggestions(Apartment $apartment): array
    {
        $currencies = $apartment->cashboxes()->where('currency', '!=', Cashbox::BASE_CURRENCY)->distinct()->pluck('currency');
        if ($currencies->isEmpty()) {
            return [];
        }

        $official = $this->officialRates();
        $suggestions = [];
        foreach ($currencies as $currency) {
            if (isset($official[$currency])) {
                $suggestions[$currency] = [
                    'rate' => $official[$currency]['rate'],
                    'source' => 'TCMB '.$official[$currency]['date'],
                ];

                continue;
            }

            $last = $apartment->cashTransactions()->where('currency', $currency)
                ->whereIn('sub_type', CashTransaction::COLLECTION_TYPES)->latest('id')->value('exchange_rate');
            if ($last !== null) {
                $suggestions[$currency] = ['rate' => (string) $last, 'source' => 'son kullanılan kur'];
            }
        }

        return $suggestions;
    }

    /** @return array<string, array{rate: string, date: string}> */
    private function officialRates(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $rates = $this->fetchOfficialRates();
        Cache::put(self::CACHE_KEY, $rates, $rates === [] ? self::FAILURE_CACHE_SECONDS : self::CACHE_SECONDS);

        return $rates;
    }

    /** @return array<string, array{rate: string, date: string}> */
    private function fetchOfficialRates(): array
    {
        try {
            $response = Http::connectTimeout(5)->timeout(8)->get(self::URL);
        } catch (ConnectionException) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body(), options: LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            return [];
        }

        $date = $this->bulletinDate((string) $xml['Tarih']);
        $rates = [];
        foreach ($xml->Currency as $currency) {
            $code = (string) $currency['CurrencyCode'];
            $unit = (int) $currency->Unit;
            $buying = (float) $currency->ForexBuying;
            if (isset(Cashbox::CURRENCIES[$code]) && $unit > 0 && $buying > 0) {
                $rates[$code] = ['rate' => number_format($buying / $unit, 6, '.', ''), 'date' => $date];
            }
        }

        return $rates;
    }

    private function bulletinDate(string $value): string
    {
        return preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)
            ? $value
            : CarbonImmutable::today()->format('d.m.Y');
    }
}
