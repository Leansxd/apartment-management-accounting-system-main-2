<x-layout heading="Banka Hareketleri" :subheading="$apartment->name.' · ekstre içe aktarma ve mutabakat'">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">Banka ekstresi yükle (CSV)</h2></div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('site.bank.import', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label" for="cashbox_id">Banka hesabı *</label>
                        <select class="input" id="cashbox_id" name="cashbox_id" required>@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                    <div><label class="label" for="file">CSV dosyası *</label><input class="input" id="file" type="file" name="file" accept=".csv,.txt" required></div>
                </div>
                <p class="text-xs text-slate-500">Başlık satırında <b>Tarih</b>, <b>Açıklama</b> ve <b>Tutar</b> (veya <b>Giriş/Çıkış</b>) sütunları olmalıdır; ayraç <code>;</code> veya <code>,</code>. Excel'den "CSV olarak kaydet" yeterlidir.
                    Açıklamada daire (A-5) veya üye adı geçen gelen satırlar otomatik eşleşir. Aynı ekstreyi tekrar yüklemek mükerrer kayıt oluşturmaz.</p>
                <button class="btn btn-primary">Yükle</button>
            </form>
        </section>
        <section class="card">
            <div class="card-head"><h2 class="card-title">Durum</h2></div>
            <div class="card-body space-y-2 text-sm">
                @foreach (['pending' => 'Bekleyen', 'posted' => 'İşlenen', 'ignored' => 'Yok sayılan'] as $k => $label)
                    <p class="flex justify-between"><span>{{ $label }}</span><b class="tabular-nums">{{ $counts[$k]->c ?? 0 }} · {{ tl($counts[$k]->s ?? 0) }}</b></p>
                @endforeach
                <form method="POST" action="{{ route('site.bank.post-matched', $apartment) }}" onsubmit="return confirm('Eşleşen tüm gelen satırlar tahsilata çevrilsin mi?')">
                    @csrf<button class="btn btn-primary w-full" @disabled(! $matchedPending)>Eşleşenleri toplu işle ({{ $matchedPending }})</button>
                </form>
            </div>
        </section>
    </div>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Ekstre satırları</h2>
            <div class="flex gap-1">@foreach (['pending' => 'Bekleyen', 'posted' => 'İşlenen', 'ignored' => 'Yok sayılan'] as $k => $label)
                <a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.bank.index', [$apartment, 'status' => $k]) }}">{{ $label }}</a>@endforeach</div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Açıklama</th><th class="num">Tutar</th><th>Daire</th><th></th></tr></thead>
                <tbody>
                @forelse ($lines as $l)
                    <tr>
                        <td class="whitespace-nowrap">{{ $l->line_date->format('d.m.Y') }}</td>
                        <td>{{ $l->description }}@if ($l->reference)<span class="block font-mono text-[11px] text-slate-400">{{ $l->reference }}</span>@endif</td>
                        <td class="num font-semibold {{ $l->amount > 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $l->amount > 0 ? '+' : '−' }}{{ tl(abs($l->amount)) }}</td>
                        <td>
                            @if ($status === 'pending' && $l->amount > 0)
                                <form method="POST" action="{{ route('site.bank.assign', [$apartment, $l]) }}" class="flex gap-1">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="u-{{ $l->id }}">Daire</label>
                                    <select class="input !w-auto !py-1 text-xs" id="u-{{ $l->id }}" name="unit_id" onchange="this.form.submit()">
                                        <option value="">Daire yok (genel gelir)</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected($l->unit_id === $u->id)>{{ $u->label() }}</option>@endforeach
                                    </select>
                                </form>
                            @else{{ $l->unit?->label() ?? '—' }}@endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            @if ($status === 'pending')
                                <form method="POST" action="{{ route('site.bank.post', [$apartment, $l]) }}" class="inline-flex gap-1">
                                    @csrf
                                    @unless ($l->amount > 0 && $l->unit_id)
                                        <label class="sr-only" for="c-{{ $l->id }}">Kategori</label>
                                        <select class="input !w-auto !py-1 text-xs" id="c-{{ $l->id }}" name="category_id"><option value="">Kategori</option>@foreach ($categories->where('type', $l->amount > 0 ? 'income' : 'expense') as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                                    @endunless
                                    <button class="btn btn-primary btn-sm">{{ $l->amount > 0 && $l->unit_id ? 'Tahsil et' : ($l->amount > 0 ? 'Gelir yaz' : 'Gider yaz') }}</button>
                                </form>
                                <form method="POST" action="{{ route('site.bank.ignore', [$apartment, $l]) }}" class="inline">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">Yok say</button></form>
                            @elseif ($l->cash_transaction_id)
                                <a class="btn btn-secondary btn-sm" href="{{ route('site.transactions.receipt', [$apartment, $l->cash_transaction_id]) }}">Makbuz</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Satır yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $lines->links() }}</div>
    </section>
</x-layout>
