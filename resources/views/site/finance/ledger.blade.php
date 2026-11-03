<x-layout heading="Yevmiye Defteri" subheading="Çift taraflı muhasebe kayıtları (yalnızca eklenir, değiştirilemez)">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Toplam Borç</p><p class="stat-value">{{ tl($totals->debit) }}</p></div>
        <div class="stat"><p class="stat-label">Toplam Alacak</p><p class="stat-value">{{ tl($totals->credit) }}</p></div>
        <div class="stat"><p class="stat-label">Denge</p>
            @if (round($totals->debit - $totals->credit, 2) == 0)<p class="stat-value text-emerald-600">Dengede ✓</p>
            @else<p class="stat-value text-rose-600">Fark: {{ tl($totals->debit - $totals->credit) }}</p>@endif</div>
    </div>
    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Referans</th><th>Tür</th><th>Hesap</th><th>Açıklama</th><th class="num">Borç</th><th class="num">Alacak</th></tr></thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500">{{ $e->created_at->format('d.m.Y H:i') }}</td>
                        <td class="font-mono text-xs">{{ $e->reference }}</td>
                        <td class="text-xs">{{ $e->entry_type }}</td>
                        <td>{{ $e->account_name }}</td>
                        <td class="text-xs">{{ $e->description }}</td>
                        <td class="num">{{ (float) $e->debit ? tl($e->debit) : '' }}</td>
                        <td class="num">{{ (float) $e->credit ? tl($e->credit) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Kayıt yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $entries->links() }}</div>
    </section>
</x-layout>
