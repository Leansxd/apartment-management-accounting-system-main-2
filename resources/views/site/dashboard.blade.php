<x-layout :heading="$apartment->name" subheading="Genel durum">
    <x-slot:actions>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
            <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
            <button class="btn btn-secondary">Filtrele</button>
            @if ($from || $to)<a class="btn btn-secondary" href="{{ route('site.dashboard', $apartment) }}">Temizle</a>@endif
        </form>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Kasa & Banka</p><p class="stat-value text-brand-600">{{ tl($summary['balance']) }}</p></div>
        <div class="stat"><p class="stat-label">Bekleyen Alacak</p><p class="stat-value">{{ tl($summary['receivables']) }}</p><p class="text-xs text-rose-600 mt-1">Gecikmiş: {{ tl($summary['overdue']) }}</p></div>
        <div class="stat"><p class="stat-label">Gelir {{ $from || $to ? '(aralık)' : '' }}</p><p class="stat-value text-emerald-600">{{ tl($summary['income']) }}</p><p class="text-xs text-slate-500 mt-1">POS: {{ tl($summary['pos']) }}</p></div>
        <div class="stat"><p class="stat-label">Gider {{ $from || $to ? '(aralık)' : '' }}</p><p class="stat-value text-rose-600">{{ tl($summary['expense']) }}</p><p class="text-xs text-slate-500 mt-1">Onay bekleyen: {{ tl($summary['pending_expenses']) }}</p></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Tahsilat Oranı</p><p class="stat-value">%{{ number_format($performance['rate'], 1, ',', '.') }}</p>
            <div class="mt-2 h-2 rounded bg-slate-100"><div class="h-2 rounded bg-brand-500" style="width: {{ min(100, $performance['rate']) }}%"></div></div></div>
        <div class="stat"><p class="stat-label">Daire / Sakin</p><p class="stat-value">{{ $counts['units'] }} / {{ $counts['members'] }}</p><p class="text-xs text-slate-500 mt-1">Mobil bağlı: {{ $counts['linked'] }}</p></div>
        <div class="stat"><p class="stat-label">Daire Avansları</p><p class="stat-value">{{ tl($summary['advances']) }}</p></div>
        <div class="stat"><p class="stat-label">Açık Talep</p><p class="stat-value">{{ $counts['tickets'] }}</p></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">Son 6 Ay Gelir / Gider</h2>
                <span class="flex gap-3 text-xs text-slate-500"><span><i class="inline-block h-2 w-2 rounded-full bg-brand-500"></i> Gelir</span><span><i class="inline-block h-2 w-2 rounded-full bg-rose-400"></i> Gider</span></span></div>
            @php $max = max(1, collect($monthly)->flatMap(fn ($m) => [$m['income'], $m['expense']])->max()); @endphp
            <div class="card-body">
                <div class="flex h-48 items-end gap-3" role="img" aria-label="Aylık gelir gider grafiği">
                    @foreach ($monthly as $m)
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div class="flex h-40 w-full items-end justify-center gap-1">
                                <div class="w-1/3 rounded-t bg-brand-500" style="height: {{ $m['income'] / $max * 100 }}%" title="Gelir {{ tl($m['income']) }}"></div>
                                <div class="w-1/3 rounded-t bg-rose-400" style="height: {{ $m['expense'] / $max * 100 }}%" title="Gider {{ tl($m['expense']) }}"></div>
                            </div>
                            <span class="text-[11px] text-slate-500">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">En Çok Gecikmiş Daireler</h2><a class="text-xs text-brand-600" href="{{ route('site.reminders.index', $apartment) }}">Tümü</a></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($overdue as $row)
                    <li class="flex justify-between px-5 py-2.5 text-sm">
                        <a class="hover:text-brand-600" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $row['unit']->id]) }}">{{ $row['unit']->label() }} <span class="text-xs text-slate-400">({{ $row['count'] }} borç)</span></a>
                        <span class="font-semibold text-rose-600 tabular-nums">{{ tl($row['total']) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-slate-500">Gecikmiş borç yok.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Son Kasa Hareketleri</h2><a class="text-xs text-brand-600" href="{{ route('site.cashboxes.index', $apartment) }}">Tümü</a></div>
        @include('site.finance._transactions', ['transactions' => $recent])
    </section>
</x-layout>
