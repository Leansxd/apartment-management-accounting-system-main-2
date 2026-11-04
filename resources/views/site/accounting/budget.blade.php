<x-layout :heading="'Bütçe '.$year" :subheading="$apartment->name.' · planlanan ve gerçekleşen'">
    <x-slot:actions>
        <form method="GET" class="flex items-end gap-2">
            <div><label class="label" for="year">Yıl</label><select class="input" id="year" name="year" onchange="this.form.submit()">@foreach ($years as $y)<option @selected($y === $year)>{{ $y }}</option>@endforeach</select></div>
        </form>
        <form method="POST" action="{{ route('site.budget.copy', $apartment) }}" class="flex items-end">@csrf<input type="hidden" name="year" value="{{ $year }}"><button class="btn btn-secondary">Önceki yılı kopyala</button></form>
    </x-slot:actions>

    @php $t = $budget['totals']; @endphp
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Planlanan gelir</p><p class="stat-value">{{ tl($t['income_planned']) }}</p></div>
        <div class="stat"><p class="stat-label">Gerçekleşen gelir</p><p class="stat-value text-emerald-600">{{ tl($t['income_actual']) }}</p></div>
        <div class="stat"><p class="stat-label">Planlanan gider</p><p class="stat-value">{{ tl($t['expense_planned']) }}</p></div>
        <div class="stat"><p class="stat-label">Gerçekleşen gider</p><p class="stat-value text-rose-600">{{ tl($t['expense_actual']) }}</p></div>
    </div>

    <form method="POST" action="{{ route('site.budget.save', $apartment) }}" class="space-y-6">
        @csrf<input type="hidden" name="year" value="{{ $year }}">
        @foreach (['income' => 'Gelirler', 'expense' => 'Giderler'] as $type => $title)
            <section class="card">
                <div class="card-head"><h2 class="card-title">{{ $title }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Kategori</th><th class="num">Bütçe (₺)</th>@if ($type === 'income')<th class="num">Tahakkuk</th>@endif<th class="num">{{ $type === 'income' ? 'Tahsilat' : 'Ödenen' }}</th><th class="num">Fark</th><th style="min-width:9rem">Gerçekleşme</th></tr></thead>
                        <tbody>
                        @forelse ($budget[$type] as $row)
                            <tr>
                                <td class="font-medium">{{ $row['category']->name }}</td>
                                <td class="num"><label class="sr-only" for="b-{{ $row['category']->id }}">{{ $row['category']->name }} bütçesi</label><input class="input !w-32 text-right" id="b-{{ $row['category']->id }}" type="number" step="0.01" min="0" name="amounts[{{ $row['category']->id }}]" value="{{ $row['planned'] ?: '' }}"></td>
                                @if ($type === 'income')<td class="num">{{ tl($row['accrued']) }}</td>@endif
                                <td class="num font-semibold">{{ tl($row['actual']) }}</td>
                                <td class="num {{ ($type === 'income' ? $row['diff'] < 0 : $row['diff'] > 0) && $row['planned'] ? 'text-rose-600' : 'text-emerald-600' }}">{{ $row['planned'] ? tl($row['diff']) : '—' }}</td>
                                <td>@if ($row['pct'] !== null)<div class="h-2 rounded bg-slate-100"><div class="h-2 rounded {{ $type === 'expense' && $row['pct'] > 100 ? 'bg-rose-500' : 'bg-brand-500' }}" style="width: {{ min(100, $row['pct']) }}%"></div></div><span class="text-[11px] text-slate-500">%{{ number_format($row['pct'], 1, ',', '.') }}</span>@else<span class="text-xs text-slate-400">bütçe yok</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-slate-500">Kategori yok. Kasa & Banka sayfasından kategori ekleyin.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
        <button class="btn btn-primary">Bütçeyi kaydet</button>
    </form>
</x-layout>
