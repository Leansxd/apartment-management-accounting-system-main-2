<x-layout heading="Gelir Gider Tablosu" :subheading="$apartment->name.' · '.\Carbon\Carbon::parse($from)->format('d.m.Y').' – '.\Carbon\Carbon::parse($to)->format('d.m.Y')">
    <x-slot:actions>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
            <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
            <button class="btn btn-secondary">Göster</button>
            <button type="button" class="btn btn-secondary" onclick="window.print()">Yazdır</button>
        </form>
    </x-slot:actions>

    @php $totalIncome = $income->sum('total'); $totalExpense = $expense->sum('total'); @endphp
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Toplam Gelir</p><p class="stat-value text-emerald-600">{{ tl($totalIncome) }}</p></div>
        <div class="stat"><p class="stat-label">Toplam Gider</p><p class="stat-value text-rose-600">{{ tl($totalExpense) }}</p></div>
        <div class="stat"><p class="stat-label">Fark</p><p class="stat-value {{ $totalIncome - $totalExpense < 0 ? 'text-rose-600' : '' }}">{{ tl($totalIncome - $totalExpense) }}</p></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach (['Gelirler' => $income, 'Giderler' => $expense] as $title => $rows)
            <section class="card">
                <div class="card-head"><h2 class="card-title">{{ $title }}</h2></div>
                <table class="table">
                    <thead><tr><th>Kalem</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead>
                    <tbody>
                    @forelse ($rows->sortByDesc('total') as $label => $row)
                        <tr><td>{{ $label }}</td><td class="num">{{ $row['count'] }}</td><td class="num font-medium">{{ tl($row['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-slate-500">Kayıt yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach
    </div>
    <p class="text-xs text-slate-500">Kasalar arası virmanlar gelir/gider sayılmaz.</p>
</x-layout>
