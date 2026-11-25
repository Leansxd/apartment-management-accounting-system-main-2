{{-- Dairenin borç ve ödeme tabloları. \$debts, \$payments --}}
    <section class="card">
        <div class="card-head"><h2 class="card-title">Borçlar</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Açıklama</th><th>Sorumlu</th><th>Vade</th><th class="num">Tutar</th><th class="num">Ödenen</th><th class="num">Kalan</th><th>Durum</th></tr></thead>
                <tbody>
                @forelse ($debts as $d)
                    <tr><td>{{ $d->description }}</td><td>{{ \App\Models\Debt::RESPONSIBLES[$d->responsible] ?? 'Daire geneli' }}</td><td>{{ $d->due_date->format('d.m.Y') }}</td><td class="num">{{ tl($d->amount) }}</td><td class="num">{{ tl($d->paid_amount) }}</td><td class="num font-semibold">{{ tl($d->remaining_amount) }}</td><td><x-debt-status :debt="$d" /></td></tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Borç kaydı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Ödemeler</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th>Ödeyen</th><th class="num">Tutar</th><th class="num">Borca</th><th class="num">Avansa</th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr><td>{{ $p->created_at->format('d.m.Y H:i') }}</td><td>{{ $p->description }}</td><td><x-member-link :member="$p->payer" fallback="Daire adına" /></td><td class="num font-semibold">{{ tl($p->amount) }}</td><td class="num">{{ tl($p->applied_to_debts) }}</td><td class="num">{{ tl($p->to_advance) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Ödeme yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
