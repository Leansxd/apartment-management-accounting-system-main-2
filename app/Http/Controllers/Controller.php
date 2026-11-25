<?php

namespace App\Http\Controllers;

use App\Services\FinanceException;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /** İşlemi çalıştırır; muhasebe kuralı ihlalinde formu korur ve hatayı gösterir. */
    protected function attempt(callable $operation, string $success): RedirectResponse
    {
        try {
            $result = $operation();
        } catch (FinanceException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', is_string($result) ? $result : $success);
    }

    /** Filtre için YYYY-AA-GG biçimindeki sorgu tarihini döner; geçersiz değer filtre uygulanmamış sayılır. */
    /** Rapor dönemini "Dönem: 01.01.2026 – 31.12.2026" biçiminde yazar; tarih yoksa null */
    protected function periodLabel(?string $from, ?string $to): ?string
    {
        if (! $from && ! $to) {
            return null;
        }
        $date = fn (?string $value, string $empty) => $value ? Carbon::parse($value)->format('d.m.Y') : $empty;

        return 'Dönem: '.$date($from, 'başlangıç').' – '.$date($to, today()->format('d.m.Y'));
    }

    protected function dateQuery(Request $request, string $key): ?string
    {
        $value = (string) $request->query($key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? $value : null;
    }

    /** Doğrulanmış para alanını kuruşa çevirir */
    protected function cents(string|int|float|null $value): int
    {
        return Money::parse($value);
    }
}
