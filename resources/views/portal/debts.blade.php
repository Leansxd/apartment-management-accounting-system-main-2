<x-portal-layout title="Borçlarım" :apartment="$apartment">
    @if ($units->count() > 1)
        <form method="GET" class="max-w-xs">
            <label class="label" for="unit_id">Daire</label>
            <select class="input" id="unit_id" name="unit_id" onchange="this.form.submit()">
                @foreach ($units as $unit)<option value="{{ $unit->id }}" @selected($unit->id === $unitId)>{{ $unit->label() }}</option>@endforeach
            </select>
        </form>
    @endif

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="stat"><p class="stat-label">Açık borç</p><p class="stat-value">{{ tl($statement['open_debt']) }}</p></div>
        <div class="stat"><p class="stat-label">Avans bakiyesi</p><p class="stat-value metric-good">{{ tl($statement['credit']) }}</p></div>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Borçlar</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Açıklama</th><th>Vade</th><th class="num">Tutar</th><th class="num">Kalan</th><th>Durum</th></tr></thead>
                <tbody>
                @forelse ($debts as $debt)
                    <tr>
                        <td>{{ $debt->description }}</td>
                        <td class="whitespace-nowrap">{{ $debt->due_date->format('d.m.Y') }}</td>
                        <td class="num">{{ tl($debt->amount) }}</td>
                        <td class="num font-semibold">{{ tl($debt->remaining_amount) }}</td>
                        <td><x-debt-status :debt="$debt" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Borç kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $debts->links() }}</div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Hesap ekstresi</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th class="num">Borç</th><th class="num">Ödeme</th><th class="num">Bakiye</th></tr></thead>
                <tbody>
                @forelse (array_reverse($statement['rows']) as $row)
                    <tr>
                        <td class="whitespace-nowrap">{{ $row['date']->format('d.m.Y') }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="num">{{ $row['debit'] ? tl($row['debit']) : '' }}</td>
                        <td class="num">{{ $row['credit'] ? tl($row['credit']) : '' }}</td>
                        <td class="num font-semibold">{{ tl($row['balance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Hareket yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-portal-layout>
