<?php

use App\Support\Money;
use Illuminate\Support\HtmlString;

if (! function_exists('tour')) {
    /** Buton / alanı anlatım motoruna tanıtan HTML öznitelikleri */
    function tour(int $order, string $title, string $text): HtmlString
    {
        return new HtmlString('data-tour-step="'.$order.'" data-tour-title="'.e($title).'" data-tour-text="'.e($text).'"');
    }
}

if (! function_exists('tl')) {
    /** Görünümlerde para biçimi: 1234.5 -> "1.234,50 ₺" */
    function tl(string|int|float|null $value): string
    {
        return Money::format($value);
    }
}
