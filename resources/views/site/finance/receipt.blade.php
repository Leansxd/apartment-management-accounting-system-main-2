@php
    $income = $tx->type === 'income';
    $hasPartDetail = $parts->count() > 1 || $tx->isForeign();
    $effectiveParts = $parts->reject->isReversed();
    $partTotal = fn (string $column) => $effectiveParts->sum(fn ($part) => \App\Support\Money::cents($part->{$column})) / 100;
    $totalAmount = $parts->count() > 1 ? $partTotal('amount') : (float) $tx->amount;
    $appliedAmount = $parts->count() > 1 ? $partTotal('applied_to_debts') : (float) $tx->applied_to_debts;
    $advanceAmount = $parts->count() > 1 ? $partTotal('to_advance') : (float) $tx->to_advance;
    $title = $income ? 'Tahsilat Makbuzu' : 'Ödeme Belgesi';
    $payer = $tx->payer ? $tx->payer->full_name.' ('.(\App\Models\Member::TYPES[$tx->payer->type] ?? '').')' : $tx->unit?->members->pluck('full_name')->implode(', ');
@endphp
<x-layout :heading="$title" :subheading="$tx->receiptNo()">
    <x-slot:actions>
        <a class="btn btn-secondary" href="{{ url()->previous() }}">← Geri</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" :size="15" />Yazdır / PDF</button>
    </x-slot:actions>

    <section class="card mx-auto max-w-3xl report-header" data-report-title="{{ $title }}">
        <div class="card-body space-y-5 p-8">
            {{-- Antet --}}
            <div class="flex flex-wrap items-end justify-between gap-4 border-b-2 border-slate-300 pb-4">
                <div>
                    <p class="text-lg font-bold uppercase text-slate-900">{{ $apartment->name }}</p>
                    <p class="text-sm text-slate-500">{{ trim($apartment->address.' '.$apartment->city) }}</p>
                    @if ($apartment->tax_number)<p class="text-xs text-slate-500">Vergi No: {{ $apartment->tax_number }}</p>@endif
                </div>
                <table class="text-sm" style="border-collapse: collapse">
                    <tr><td class="border border-slate-300 px-3 py-1 text-slate-500">Makbuz No</td><td class="border border-slate-300 px-3 py-1 font-mono font-bold">{{ $tx->receiptNo() }}</td></tr>
                    <tr><td class="border border-slate-300 px-3 py-1 text-slate-500">Tarih</td><td class="border border-slate-300 px-3 py-1">{{ $tx->created_at->format('d.m.Y H:i') }}</td></tr>
                </table>
            </div>
            <p class="text-center text-xl font-black uppercase tracking-widest text-slate-800">{{ $title }}</p>

            @if ($tx->isReversed())<p class="border-2 border-red-400 px-4 py-2 text-center text-sm font-bold text-red-600">BU İŞLEM İPTAL EDİLMİŞTİR — {{ $tx->reversal_reason }}</p>@endif

            <table class="table" data-print-whole>
                <tbody>
                    @if ($tx->unit)
                        <tr><th class="w-44">Daire</th><td class="font-semibold"><x-unit-link :unit="$tx->unit" /></td></tr>
                        <tr><th>{{ $income ? 'Ödeyen' : 'Alıcı' }}</th><td class="font-semibold">@if ($tx->payer)<x-member-link :member="$tx->payer" /> <span class="text-xs font-normal text-slate-500">({{ \App\Models\Member::TYPES[$tx->payer->type] ?? '' }})</span>@else{{ $payer ?: '—' }}@endif</td></tr>
                    @endif
                    <tr><th>Kasa / Banka</th><td>{{ $parts->map(fn ($part) => $part->cashbox?->labelWithCurrency())->unique()->implode(', ') }}</td></tr>
                    <tr><th>İşlem türü</th><td>{{ \App\Models\CashTransaction::SUB_TYPES[$tx->sub_type] ?? $tx->sub_type }}</td></tr>
                    <tr><th>Açıklama</th><td>{{ $tx->description }}</td></tr>
                </tbody>
            </table>

            @if ($hasPartDetail)
                <div>
                    <p class="mb-2 text-sm font-semibold">Ödeme parçaları</p>
                    <table class="table" data-print-whole>
                        <thead><tr><th>Parça</th><th>Kasa / Banka</th><th>Ödeme şekli</th><th class="num">Ödenen</th><th class="num">Kur</th><th class="num">TL karşılığı</th></tr></thead>
                        <tbody>
                        @foreach ($parts as $part)
                            <tr class="{{ $part->isReversed() ? 'opacity-50' : '' }}">
                                <td class="font-mono text-xs">{{ $part->receiptNo() }}@if ($part->isReversed()) <span class="badge badge-red">İptal</span>@endif</td>
                                <td>{{ $part->cashbox?->name }}</td>
                                <td>{{ \App\Models\CashTransaction::SUB_TYPES[$part->sub_type] ?? $part->sub_type }}</td>
                                <td class="num">{{ money($part->original_amount, $part->currency) }}</td>
                                <td class="num">{{ $part->isForeign() ? \App\Support\Money::formatRate($part->exchange_rate) : '—' }}</td>
                                <td class="num font-semibold">{{ tl($part->amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($income && $payments->isNotEmpty())
                <div>
                    <p class="mb-2 text-sm font-semibold">Kapatılan borçlar</p>
                    <table class="table">
                        <thead><tr><th>Borç</th><th>Dönem</th><th>Vade</th><th class="num">Ödenen</th><th class="num">Kalan</th></tr></thead>
                        <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>{{ $payment->debt?->description ?? 'Silinmiş borç' }}</td>
                                <td>{{ $payment->debt?->period }}</td>
                                <td class="whitespace-nowrap">{{ $payment->debt?->due_date?->format('d.m.Y') }}</td>
                                <td class="num">{{ tl($payment->amount) }}</td>
                                <td class="num">{{ $payment->debt ? tl($payment->debt->remaining_amount) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="print-keep border-2 border-slate-300 p-5">
                <div class="flex items-center justify-between gap-4"><span class="text-sm font-semibold">{{ $hasPartDetail ? 'Toplam TL karşılığı' : 'Tutar' }}</span><span class="text-3xl font-black tabular-nums text-slate-900">{{ tl($totalAmount) }}</span></div>
                <p class="mt-2 border-t border-slate-200 pt-2 text-sm">Yalnız: <b>{{ \App\Support\Money::inWords($totalAmount) }}</b>'dır.</p>
                @if ($income && ($appliedAmount || $advanceAmount))
                    <div class="mt-2 flex justify-between text-sm"><span>Borçlara uygulanan</span><b class="tabular-nums">{{ tl($appliedAmount) }}</b></div>
                    <div class="flex justify-between text-sm"><span>Avans (fazla ödeme)</span><b class="tabular-nums">{{ tl($advanceAmount) }}</b></div>
                @endif
            </div>

            <p class="text-xs text-slate-500">Yukarıda yazılı tutar {{ $income ? 'tahsil edilmiştir' : 'ödenmiştir' }}. Bu belge {{ $apartment->name }} yönetimi adına {{ $tx->performer?->name ?? 'yönetim' }} tarafından düzenlenmiştir.</p>

            <div class="print-keep grid grid-cols-2 gap-10 pt-12 text-center text-xs">
                <div class="border-t border-slate-400 pt-2"><b>Teslim eden{{ $income ? ' (Ödeyen)' : '' }}</b><br>Ad Soyad / İmza</div>
                <div class="border-t border-slate-400 pt-2"><b>Teslim alan</b> — {{ $tx->performer?->name ?? 'Yönetim' }}<br>İmza / Kaşe</div>
            </div>
        </div>
    </section>
</x-layout>
