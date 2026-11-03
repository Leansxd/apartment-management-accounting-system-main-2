<?php

namespace App\Http\Controllers;

use App\Services\FinanceException;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;

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

    /** Doğrulanmış para alanını kuruşa çevirir */
    protected function cents(string|int|float|null $value): int
    {
        return Money::parse($value);
    }
}
