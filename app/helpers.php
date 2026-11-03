<?php

use App\Support\Money;

if (! function_exists('tl')) {
    /** Görünümlerde para biçimi: 1234.5 -> "1.234,50 ₺" */
    function tl(string|int|float|null $value): string
    {
        return Money::format($value);
    }
}
