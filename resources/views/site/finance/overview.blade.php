<x-layout heading="Finans Özeti" :subheading="$apartment->name">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('site.collections.index', $apartment) }}">Tahsilat al</a>
        <a class="btn btn-secondary" href="{{ route('site.debts.index', $apartment) }}">Borç yaz</a>
        <a class="btn btn-secondary" href="{{ route('site.expenses.index', $apartment) }}">Gider gir</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Mevcut Likidite</p><p class="stat-value text-brand-600">{{ tl($summary['balance']) }}</p><p class="text-xs text-slate-500 mt-1">Kasa & banka toplamı</p></div>
        <div class="stat"><p class="stat-label">Gelecek Tahsilat</p><p class="stat-value">{{ tl($summary['receivables']) }}</p><p class="text-xs text-rose-600 mt-1">Gecikmiş: {{ tl($summary['overdue']) }}</p></div>
        <div class="stat"><p class="stat-label">POS İşlem Hacmi</p><p class="stat-value">{{ tl($summary['pos']) }}</p><p class="text-xs text-slate-500 mt-1">Kredi kartı tahsilatları</p></div>
        <div class="stat"><p class="stat-label">Finansal Yük</p><p class="stat-value text-rose-600">{{ tl($summary['pending_expenses']) }}</p><p class="text-xs text-slate-500 mt-1">Ödenmeyi bekleyen gider</p></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Kasalar</h2><a class="text-xs text-brand-600" href="{{ route('site.cashboxes.index', $apartment) }}">Yönet</a></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($cashboxes as $c)
                    <li class="flex justify-between px-5 py-2.5 text-sm"><span>{{ $c->name }} <span class="text-xs text-slate-400">{{ \App\Models\Cashbox::TYPES[$c->type] }}</span></span>
                        <span class="font-semibold tabular-nums {{ $c->balance < 0 ? 'text-rose-600' : '' }}">{{ tl($c->balance) }}</span></li>
                @endforeach
            </ul>
        </section>
        <section class="card">
            <div class="card-head"><h2 class="card-title">Tahsilat Performansı</h2></div>
            <div class="card-body space-y-2 text-sm">
                <p class="flex justify-between"><span>Tahakkuk edilen</span><b class="tabular-nums">{{ tl($performance['accrued']) }}</b></p>
                <p class="flex justify-between"><span>Tahsil edilen</span><b class="tabular-nums text-emerald-600">{{ tl($performance['collected']) }}</b></p>
                <div class="h-2 rounded bg-slate-100"><div class="h-2 rounded bg-brand-500" style="width: {{ min(100, $performance['rate']) }}%"></div></div>
                <p class="text-right text-xs text-slate-500">%{{ number_format($performance['rate'], 1, ',', '.') }}</p>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><h2 class="card-title">Onay Bekleyen Giderler</h2><a class="text-xs text-brand-600" href="{{ route('site.expenses.index', [$apartment, 'status' => 'pending']) }}">Tümü</a></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($pendingExpenses as $e)
                    <li class="flex justify-between px-5 py-2.5 text-sm"><span>{{ $e->description }} <span class="text-xs text-slate-400">{{ $e->vendor?->name }}</span></span><b class="tabular-nums">{{ tl($e->total_amount) }}</b></li>
                @empty
                    <li class="px-5 py-3 text-sm text-slate-500">Bekleyen gider yok.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Son Hareketler</h2></div>
        @include('site.finance._transactions', ['transactions' => $recent])
    </section>
</x-layout>
