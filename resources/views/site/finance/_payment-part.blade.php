{{-- Parçalı tahsilat satırı. $index: dizi anahtarı, $row: eski form değerleri, $cashboxes: sitenin kasaları --}}
@php
    $selectedBox = $cashboxes->firstWhere('id', (int) ($row['cashbox_id'] ?? 0)) ?? $cashboxes->firstWhere('currency', \App\Models\Cashbox::BASE_CURRENCY) ?? $cashboxes->first();
    $isForeignPart = $selectedBox?->isForeign() ?? false;
@endphp
<div class="rounded-xl border border-slate-200 p-3" data-payment-part>
    <div class="grid gap-3 sm:grid-cols-12">
        <div class="sm:col-span-4"><label class="label" for="part-{{ $index }}-cashbox">Kasa / Banka *</label>
            <select class="input" id="part-{{ $index }}-cashbox" name="parts[{{ $index }}][cashbox_id]" data-part-cashbox required>
                @foreach ($cashboxes as $c)<option value="{{ $c->id }}" data-currency="{{ $c->currency }}" @selected($selectedBox?->id === $c->id)>{{ $c->labelWithCurrency() }}</option>@endforeach
            </select></div>
        <div class="sm:col-span-3"><label class="label" for="part-{{ $index }}-amount">Tutar (<span data-part-currency>{{ $selectedBox?->currency ?? 'TRY' }}</span>) *</label>
            <input class="input" id="part-{{ $index }}-amount" type="number" step="0.01" min="0.01" name="parts[{{ $index }}][amount]" value="{{ $row['amount'] ?? '' }}" data-part-amount required></div>
        <div class="sm:col-span-2" data-part-rate-box @if (! $isForeignPart) hidden @endif><label class="label" for="part-{{ $index }}-rate">Kur (₺) *</label>
            <input class="input" id="part-{{ $index }}-rate" type="number" step="0.000001" min="0.000001" name="parts[{{ $index }}][exchange_rate]" value="{{ $row['exchange_rate'] ?? '' }}" data-part-rate @disabled(! $isForeignPart) @required($isForeignPart)></div>
        <div class="sm:col-span-3"><label class="label" for="part-{{ $index }}-method">Ödeme şekli</label>
            <select class="input" id="part-{{ $index }}-method" name="parts[{{ $index }}][method]">
                @foreach (['collection' => 'Nakit / Havale', 'pos' => 'POS / Kredi kartı', 'advance' => 'Avans (peşin ödeme)'] as $value => $label)<option value="{{ $value }}" @selected(($row['method'] ?? 'collection') === $value)>{{ $label }}</option>@endforeach
            </select></div>
    </div>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <span data-part-rate-hint></span>
        <span class="flex items-center gap-3">
            <span>TL karşılığı: <b class="tabular-nums" data-part-try>—</b></span>
            <button type="button" class="btn btn-secondary btn-sm" data-remove-part>Parçayı kaldır</button>
        </span>
    </div>
</div>
