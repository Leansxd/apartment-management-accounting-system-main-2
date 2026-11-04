@php $income = $tx->type === 'income'; @endphp
<x-layout :heading="($income ? 'Tahsilat' : 'Ödeme').' Makbuzu'" :subheading="$tx->receiptNo()">
    <x-slot:actions>
        <a class="btn btn-secondary" href="{{ url()->previous() }}">← Geri</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır / PDF</button>
    </x-slot:actions>

    <section class="card mx-auto max-w-3xl">
        <div class="card-body space-y-6 p-8">
            <div class="flex flex-wrap justify-between gap-4 border-b border-slate-100 pb-6">
                <div>
                    <p class="text-lg font-bold text-slate-900">{{ $apartment->name }}</p>
                    <p class="text-sm text-slate-500">{{ $apartment->address }} {{ $apartment->city }}</p>
                    @if ($apartment->tax_number)<p class="text-xs text-slate-400">Vergi No: {{ $apartment->tax_number }}</p>@endif
                </div>
                <div class="text-right">
                    <p class="text-sm font-black uppercase tracking-widest text-slate-700">{{ $income ? 'Tahsilat' : 'Ödeme' }} Makbuzu</p>
                    <p class="font-mono text-lg font-bold">{{ $tx->receiptNo() }}</p>
                    <p class="text-sm text-slate-500">{{ $tx->created_at->format('d.m.Y H:i') }}</p>
                </div>
            </div>

            @if ($tx->isReversed())<p class="rounded-sm border border-red-100 bg-red-50 px-4 py-2 text-sm font-bold text-red-600">BU İŞLEM İPTAL EDİLMİŞTİR — {{ $tx->reversal_reason }}</p>@endif

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @if ($tx->unit)
                    <div><dt class="label">Daire</dt><dd class="font-semibold">{{ $tx->unit->label() }}</dd></div>
                    <div><dt class="label">{{ $income ? 'Ödeyen' : 'Alıcı' }}</dt><dd class="font-semibold">{{ $tx->unit->members->pluck('full_name')->implode(', ') ?: '—' }}</dd></div>
                @endif
                <div><dt class="label">Kasa / Banka</dt><dd>{{ $tx->cashbox?->name }}</dd></div>
                <div><dt class="label">İşlem türü</dt><dd>{{ \App\Models\CashTransaction::SUB_TYPES[$tx->sub_type] ?? $tx->sub_type }}</dd></div>
                <div class="sm:col-span-2"><dt class="label">Açıklama</dt><dd>{{ $tx->description }}</dd></div>
            </dl>

            <div class="rounded-sm border border-slate-100 bg-slate-50/50 p-5">
                <div class="flex items-center justify-between"><span class="text-sm text-slate-500">Tutar</span><span class="text-3xl font-black tabular-nums text-slate-900">{{ tl($tx->amount) }}</span></div>
                @if ($income && ((float) $tx->applied_to_debts || (float) $tx->to_advance))
                    <div class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-sm"><span>Borçlara uygulanan</span><b class="tabular-nums">{{ tl($tx->applied_to_debts) }}</b></div>
                    <div class="flex justify-between text-sm"><span>Avans (fazla ödeme)</span><b class="tabular-nums">{{ tl($tx->to_advance) }}</b></div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-8 pt-10 text-center text-xs text-slate-500">
                <div class="border-t border-slate-300 pt-2">Teslim eden{{ $income ? ' (Ödeyen)' : '' }}</div>
                <div class="border-t border-slate-300 pt-2">Teslim alan — {{ $tx->performer?->name ?? 'Yönetim' }}</div>
            </div>
        </div>
    </section>
</x-layout>
