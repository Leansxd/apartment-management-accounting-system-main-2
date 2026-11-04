{{-- $actions = true ise makbuz ve iptal işlemleri gösterilir ($apartment gerekir) --}}
@php $actions = $actions ?? false; @endphp
<div class="overflow-x-auto">
    <table class="table">
        <thead><tr><th>Tarih</th><th>Açıklama</th><th>Daire</th><th>Kasa</th><th>Tür</th><th class="num">Tutar</th>@if ($actions)<th></th>@endif</tr></thead>
        <tbody>
        @forelse ($transactions as $t)
            <tr class="{{ $t->isReversed() ? 'opacity-50' : '' }}">
                <td class="whitespace-nowrap text-slate-500">{{ $t->created_at->format('d.m.Y H:i') }}</td>
                <td class="{{ $t->isReversed() ? 'line-through' : '' }}">{{ $t->description }}@if ($t->isReversed())<span class="badge badge-red ml-1 no-underline">İptal</span>@endif</td>
                <td>{{ $t->unit?->label() ?? '—' }}</td>
                <td>{{ $t->cashbox?->name }}</td>
                <td><span class="badge {{ $t->type === 'income' ? 'badge-green' : 'badge-red' }}">{{ \App\Models\CashTransaction::SUB_TYPES[$t->sub_type] ?? $t->sub_type }}</span></td>
                <td class="num font-semibold {{ $t->type === 'income' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $t->type === 'income' ? '+' : '−' }}{{ tl($t->amount) }}</td>
                @if ($actions)
                    <td class="whitespace-nowrap text-right">
                        <a class="btn btn-secondary btn-sm" href="{{ route('site.transactions.receipt', [$apartment, $t]) }}">Makbuz</a>
                        @if (! $t->isReversed() && $t->sub_type !== 'reversal' && ! $t->isTransfer())
                            <details class="relative inline-block text-left">
                                <summary class="btn btn-danger btn-sm">İptal</summary>
                                <form method="POST" action="{{ route('site.transactions.reverse', [$apartment, $t]) }}" class="absolute right-0 z-10 mt-1 w-72 card card-body space-y-2 shadow-lg">
                                    @csrf
                                    <label class="label" for="rv-{{ $t->id }}">İptal nedeni</label>
                                    <input class="input" id="rv-{{ $t->id }}" name="reason" required minlength="3" maxlength="150" placeholder="ör. Hatalı tutar girildi">
                                    <button class="btn btn-danger w-full" onclick="return confirm('Hareket iptal edilsin mi? Ters kayıt atılır.')">Hareketi iptal et</button>
                                </form>
                            </details>
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $actions ? 7 : 6 }}" class="text-center text-slate-500">Hareket yok.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if (method_exists($transactions, 'links'))<div class="px-5 py-3 no-print">{{ $transactions->links() }}</div>@endif
