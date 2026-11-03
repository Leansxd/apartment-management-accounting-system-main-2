<x-layout heading="Tahsilat" :subheading="$apartment->name">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">1. Daire seçin</h2></div>
            <form method="GET" class="card-body flex gap-2">
                <label class="sr-only" for="unit_id">Daire</label>
                <select class="input" id="unit_id" name="unit_id" onchange="this.form.submit()">
                    <option value="">Daire seçin…</option>
                    @foreach ($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->label() }}</option>@endforeach
                </select>
                <noscript><button class="btn btn-secondary">Seç</button></noscript>
            </form>
            @if ($unit)
                <div class="card-body pt-0 text-sm space-y-1">
                    <p><b>Sakin:</b> {{ $unit->members->pluck('full_name')->implode(', ') ?: '—' }}</p>
                    <p><b>Açık borç:</b> <span class="text-rose-600 font-semibold">{{ tl($openDebts->sum('remaining_amount')) }}</span></p>
                    <p><b>Avans:</b> {{ tl($unit->credit_balance) }}</p>
                </div>
            @endif
        </section>

        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">2. Tahsilatı kaydedin</h2><span class="text-xs text-slate-500">Önce seçilen borç, sonra en eski vadeli borçlar kapanır; artan tutar avansa yazılır</span></div>
            @if ($unit)
                <form method="POST" action="{{ route('site.collections.store', $apartment) }}" class="card-body space-y-4">
                    @csrf
                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-3"><label class="label" for="debt_id">Öncelikli borç</label>
                            <select class="input" id="debt_id" name="debt_id">
                                <option value="">En eski vadeden başla</option>
                                @foreach ($openDebts as $d)<option value="{{ $d->id }}" @selected(request('debt_id') == $d->id)>{{ $d->description }} — {{ tl($d->remaining_amount) }} ({{ $d->due_date->format('d.m.Y') }})</option>@endforeach
                            </select></div>
                        <div><label class="label" for="amount">Tutar (₺) *</label><input class="input" id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $openDebts->sum('remaining_amount') ?: '') }}" required></div>
                        <div><label class="label" for="cashbox_id">Kasa *</label>
                            <select class="input" id="cashbox_id" name="cashbox_id" required>@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                        <div><label class="label" for="method">Ödeme şekli</label>
                            <select class="input" id="method" name="method"><option value="collection">Nakit / Havale</option><option value="pos">POS / Kredi kartı</option><option value="advance">Avans (peşin ödeme)</option></select></div>
                        <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="description">Açıklama</label><input class="input" id="description" name="description" value="{{ old('description') }}" placeholder="{{ $unit->label() }} Tahsilat"></div>
                    </div>
                    <button class="btn btn-primary">Tahsilatı kaydet</button>
                </form>
                @if ($openDebts->isNotEmpty())
                    <div class="overflow-x-auto border-t border-slate-100">
                        <table class="table">
                            <thead><tr><th>Açık borç</th><th>Vade</th><th class="num">Kalan</th><th>Durum</th></tr></thead>
                            <tbody>@foreach ($openDebts as $d)<tr><td>{{ $d->description }}</td><td>{{ $d->due_date->format('d.m.Y') }}</td><td class="num">{{ tl($d->remaining_amount) }}</td><td><x-debt-status :debt="$d" /></td></tr>@endforeach</tbody>
                        </table>
                    </div>
                @endif
            @else
                <p class="card-body text-sm text-slate-500">Tahsilat almak için önce daire seçin.</p>
            @endif
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Son tahsilatlar</h2><a class="text-xs text-brand-600" href="{{ route('site.reports.show', [$apartment, 'receipts']) }}">Makbuz listesi</a></div>
        @include('site.finance._transactions', ['transactions' => $recent])
    </section>
</x-layout>
