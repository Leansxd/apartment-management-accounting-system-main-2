{{-- Resmi çıktı anteti (yalnızca yazdırırken görünür): site bilgileri, belge adı, belge no, dönem ve düzenlenme zamanı --}}
@props(['apartment', 'title', 'from' => null, 'to' => null, 'asOf' => null, 'note' => null, 'documentNo' => null])
@php
    $date = fn ($value) => \Carbon\Carbon::parse($value)->format('d.m.Y');
    $period = match (true) {
        $asOf !== null => $date($asOf).' tarihi itibarıyla',
        $from || $to => 'Dönem: '.($from ? $date($from) : 'Başlangıç').' – '.($to ? $date($to) : today()->format('d.m.Y')),
        default => null,
    };
    $documentNo ??= strtoupper(substr(hash('sha256', implode('|', [$apartment->id, $title, $from, $to, $asOf, $note, today()->toDateString()])), 0, 8));
@endphp
<div class="print-only report-header mb-4" data-report-title="{{ $title }}">
    <table class="w-full border-b-2 border-black" style="border-collapse: collapse">
        <tr>
            <td class="pb-2 align-bottom">
                <p class="text-[14pt] font-bold uppercase leading-tight">{{ $apartment->name }}</p>
                <p class="text-[8.5pt]">{{ trim(($apartment->address ?? '').' '.($apartment->city ?? '')) ?: ' ' }}</p>
                @if ($apartment->tax_number)<p class="text-[8.5pt]">Vergi No: {{ $apartment->tax_number }}</p>@endif
            </td>
            <td class="pb-2 text-right align-bottom text-[8.5pt]">
                <p>Belge No: <b>{{ $documentNo }}</b></p>
                <p>Düzenlenme: {{ now()->format('d.m.Y H:i') }}</p>
                <p>Düzenleyen: {{ auth()->user()?->name }}</p>
            </td>
        </tr>
    </table>
    <div class="mt-3 text-center">
        <p class="text-[13pt] font-bold uppercase tracking-wide">{{ $title }}</p>
        @if ($period)<p class="text-[9pt]">{{ $period }}</p>@endif
        @if ($note)<p class="text-[9pt]">{{ $note }}</p>@endif
    </div>
</div>
