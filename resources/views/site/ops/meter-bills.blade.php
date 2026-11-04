@php $v = fn (string $k, $default = null) => old($k, $input[$k] ?? $default); @endphp
<x-layout heading="Sayaç Faturası Dağıtımı" :subheading="$apartment->name.' · su, ısınma, doğalgaz faturasını dairelere paylaştır'">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Fatura bilgileri</h2><span class="text-xs text-slate-500">Paylar kuruşu kuruşuna fatura tutarına eşit olur.</span></div>
        <form method="POST" action="{{ route('site.meter-bills.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="meter_type">Sayaç türü</label><select class="input" id="meter_type" name="meter_type">@foreach (\App\Models\Meter::TYPES as $t)<option @selected($v('meter_type') === $t)>{{ $t }}</option>@endforeach</select></div>
                <div><label class="label" for="method">Dağıtım yöntemi</label><select class="input" id="method" name="method">@foreach (\App\Models\MeterBill::METHODS as $k => $l)<option value="{{ $k }}" @selected($v('method') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="label" for="period_from">Dönem başlangıcı</label><input class="input" id="period_from" type="date" name="period_from" value="{{ $v('period_from', now()->subMonth()->startOfMonth()->toDateString()) }}" required></div>
                <div><label class="label" for="period_to">Dönem bitişi</label><input class="input" id="period_to" type="date" name="period_to" value="{{ $v('period_to', now()->subMonth()->endOfMonth()->toDateString()) }}" required></div>
                <div><label class="label" for="total_amount">Fatura tutarı (₺)</label><input class="input" id="total_amount" type="number" step="0.01" min="0.01" name="total_amount" value="{{ $v('total_amount') }}" required></div>
                <div class="sm:col-span-2"><label class="label" for="description">Açıklama</label><input class="input" id="description" name="description" value="{{ $v('description') }}" placeholder="ör. Mart 2026 Su Faturası" required></div>
                <div><label class="label" for="due_date">Son ödeme tarihi</label><input class="input" id="due_date" type="date" name="due_date" value="{{ $v('due_date', now()->addDays(15)->toDateString()) }}" required></div>
            </div>
            <p class="text-xs text-slate-500">"Tüketime göre" için sayaçlar daireye bağlı tanımlanmalı ve dönem içinde okuma girilmiş olmalıdır (Sayaçlar sayfası). Paylar borç olarak yazılır.</p>
            <div class="flex gap-2">
                <button class="btn btn-secondary" name="action" value="preview">Önizle</button>
                <button class="btn btn-primary" name="action" value="save" onclick="return confirm('Fatura dairelere borç olarak yazılsın mı?')">Dağıt ve borç yaz</button>
            </div>
        </form>
    </section>

    @if ($preview)
        <section class="card border-brand-100">
            <div class="card-head"><h2 class="card-title">Önizleme · {{ $preview->count() }} daire · toplam {{ tl($preview->sum('amount') / 100) }}</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Daire</th><th class="num">{{ $input['method'] === 'consumption' ? 'Tüketim' : ($input['method'] === 'area' ? 'm²' : 'Pay') }}</th><th class="num">Tutar</th></tr></thead>
                    <tbody>@foreach ($preview as $row)<tr><td class="font-medium">{{ $row['unit']->label() }}</td><td class="num">{{ number_format($row['weight'], 3, ',', '.') }}</td><td class="num font-semibold">{{ tl($row['amount'] / 100) }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card-head"><h2 class="card-title">Geçmiş dağıtımlar</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th>Tür</th><th>Dönem</th><th>Yöntem</th><th class="num">Daire</th><th class="num">Tutar</th></tr></thead>
                <tbody>
                @forelse ($bills as $b)
                    <tr><td>{{ $b->created_at->format('d.m.Y') }}</td><td>{{ $b->description }}</td><td>{{ $b->meter_type }}</td><td class="whitespace-nowrap">{{ $b->period_from->format('d.m') }} – {{ $b->period_to->format('d.m.Y') }}</td><td>{{ \App\Models\MeterBill::METHODS[$b->method] }}</td><td class="num">{{ $b->units_count }}</td><td class="num font-semibold">{{ tl($b->total_amount) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Dağıtım yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
