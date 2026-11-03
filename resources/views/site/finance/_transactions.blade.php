<div class="overflow-x-auto">
    <table class="table">
        <thead><tr><th>Tarih</th><th>Açıklama</th><th>Daire</th><th>Kasa</th><th>Tür</th><th class="num">Tutar</th></tr></thead>
        <tbody>
        @forelse ($transactions as $t)
            <tr>
                <td class="whitespace-nowrap text-slate-500">{{ $t->created_at->format('d.m.Y H:i') }}</td>
                <td>{{ $t->description }}</td>
                <td>{{ $t->unit?->label() ?? '—' }}</td>
                <td>{{ $t->cashbox?->name }}</td>
                <td><span class="badge {{ $t->type === 'income' ? 'badge-green' : 'badge-red' }}">{{ \App\Models\CashTransaction::SUB_TYPES[$t->sub_type] ?? $t->sub_type }}</span></td>
                <td class="num font-semibold {{ $t->type === 'income' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $t->type === 'income' ? '+' : '−' }}{{ tl($t->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-slate-500">Hareket yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if (method_exists($transactions, 'links'))<div class="px-5 py-3 no-print">{{ $transactions->links() }}</div>@endif
