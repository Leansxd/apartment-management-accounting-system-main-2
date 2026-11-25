<x-layout heading="Yevmiye Defteri" subheading="Çift taraflı muhasebe kayıtları (yalnızca eklenir, değiştirilemez)">
    <x-slot:actions><x-report.actions /></x-slot:actions>

    <x-report.toolbar :range="true" :from="request('from')" :to="request('to')" :keep="['account_id' => request('account_id')]">
        <div><label class="label" for="account">Hesap</label>
            <select class="input" id="account" name="account"><option value="">Tümü</option>@foreach ($accounts as $value => $text)<option value="{{ $value }}" @selected(request('account') === $value)>{{ $text }}</option>@endforeach</select></div>
        <div><label class="label" for="type">İşlem türü</label>
            <select class="input" id="type" name="type"><option value="">Tümü</option>@foreach (\App\Models\LedgerEntry::ENTRY_TYPES as $value => $text)<option value="{{ $value }}" @selected(request('type') === $value)>{{ $text }}</option>@endforeach</select></div>
        <div><label class="label" for="q">Ara</label><input class="input" id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Açıklama veya referans"></div>
    </x-report.toolbar>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat"><p class="stat-label">Kayıt</p><p class="stat-value">{{ number_format($totals->cnt, 0, ',', '.') }}</p><p class="text-xs text-slate-500">{{ request()->anyFilled(['from', 'to', 'account', 'type', 'q', 'account_id']) ? 'filtreye uyan' : 'tüm kayıtlar' }}</p></div>
        <div class="stat"><p class="stat-label">Toplam Borç</p><p class="stat-value whitespace-nowrap text-xl">{{ tl($totals->debit) }}</p></div>
        <div class="stat"><p class="stat-label">Toplam Alacak</p><p class="stat-value whitespace-nowrap text-xl">{{ tl($totals->credit) }}</p></div>
        <div class="stat"><p class="stat-label">{{ request()->anyFilled(['account', 'account_id']) ? 'Bakiye (borç − alacak)' : 'Denge' }}</p>
            @if (request()->anyFilled(['account', 'account_id']))<p class="stat-value">{{ tl($totals->debit - $totals->credit) }}</p>
            @elseif (round($totals->debit - $totals->credit, 2) == 0)<p class="stat-value text-emerald-600">Dengede ✓</p>
            @else<p class="stat-value text-rose-600">Fark: {{ tl($totals->debit - $totals->credit) }}</p>@endif</div>
    </div>
    @if (request()->filled('account_id'))<p class="no-print text-xs text-slate-500">Yalnızca seçilen alt hesabın kayıtları gösteriliyor. <a class="text-brand-600 hover:underline" href="{{ request()->fullUrlWithQuery(['account_id' => null]) }}">Tüm alt hesapları göster</a></p>@endif

    <details class="panel card no-print">
        <summary class="card-head"><span class="card-title">+ Elle yevmiye fişi</span></summary>
        <form method="POST" action="{{ route('site.ledger.manual.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <p class="text-xs text-slate-500">Gelir, gider, kur farkı ve açılış hesapları arasında düzeltme içindir. Kasa ve daire hesapları kendi ekranlarından işlenir. Fiş mizan ve yevmiyede görünür, kasa bakiyesini ve gelir-gider tablosunu değiştirmez.</p>
            <div class="form-grid">
                <div><label class="label" for="mj-date">Tarih *</label><input class="input" id="mj-date" type="date" name="date" max="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required></div>
                <div class="sm:col-span-1 lg:col-span-3"><label class="label" for="mj-desc">Açıklama *</label><input class="input" id="mj-desc" name="description" maxlength="200" value="{{ old('description') }}" required></div>
            </div>
            @foreach (range(0, 3) as $line)
                <div class="grid gap-2 sm:grid-cols-[2fr_1fr_1fr]">
                    <div><label class="sr-only" for="mj-acc-{{ $line }}">Hesap {{ $line + 1 }}</label>
                        <select class="input" id="mj-acc-{{ $line }}" name="lines[{{ $line }}][account]">
                            <option value="">Hesap seçin</option>
                            @foreach ($categories as $category)<option value="{{ $category->type }}:{{ $category->id }}" @selected(old("lines.$line.account") === "{$category->type}:{$category->id}")>{{ $category->type === 'income' ? 'Gelir' : 'Gider' }}: {{ $category->name }}</option>@endforeach
                            <option value="fx" @selected(old("lines.$line.account") === 'fx')>Kur Farkı Hesabı</option>
                            <option value="opening" @selected(old("lines.$line.account") === 'opening')>Açılış Bakiyesi Hesabı</option>
                        </select></div>
                    <div><label class="sr-only" for="mj-d-{{ $line }}">Borç {{ $line + 1 }}</label><input class="input" id="mj-d-{{ $line }}" type="number" step="0.01" min="0" name="lines[{{ $line }}][debit]" placeholder="Borç" value="{{ old("lines.$line.debit") }}"></div>
                    <div><label class="sr-only" for="mj-c-{{ $line }}">Alacak {{ $line + 1 }}</label><input class="input" id="mj-c-{{ $line }}" type="number" step="0.01" min="0" name="lines[{{ $line }}][credit]" placeholder="Alacak" value="{{ old("lines.$line.credit") }}"></div>
                </div>
            @endforeach
            <button class="btn btn-primary">Fişi kaydet</button>
        </form>
    </details>

    <section class="card">
        <x-report.print-header :apartment="$apartment" title="Yevmiye Defteri" :from="request('from')" :to="request('to')" :note="request('account')" />
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Belge</th><th>Tür</th><th>Hesap</th><th>Açıklama</th><th class="num">Borç</th><th class="num">Alacak</th><th class="no-print"></th></tr></thead>
                <tbody>
                @php $shownManual = []; @endphp
                @forelse ($entries as $e)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500">{{ $e->created_at->format('d.m.Y H:i') }}</td>
                        <td class="whitespace-nowrap text-xs" title="{{ $e->reference }}">@if ($e->cashTransactionId())<a class="hover:text-brand-600 hover:underline" href="{{ route('site.transactions.receipt', [$apartment, $e->cashTransactionId()]) }}">{{ $e->referenceLabel() }}</a>@else{{ $e->referenceLabel() }}@endif</td>
                        <td class="text-xs">{{ \App\Models\LedgerEntry::ENTRY_TYPES[$e->entry_type] ?? $e->entry_type }}</td>
                        <td>{{ $e->account_name }}</td>
                        <td class="text-xs">{{ $e->description }}</td>
                        <td class="num">{{ (float) $e->debit ? tl($e->debit) : '' }}</td>
                        <td class="num">{{ (float) $e->credit ? tl($e->credit) : '' }}</td>
                        <td class="no-print text-right">
                            @if ($e->entry_type === 'manual' && ! in_array($e->reference, $reversedManual, true) && ! in_array($e->reference, $shownManual, true))
                                @php $shownManual[] = $e->reference; @endphp
                                <x-dash.edit-panel :action="route('site.ledger.manual.reverse', $apartment)" label="İptal et" width="w-80">
                                    <input type="hidden" name="reference" value="{{ $e->reference }}">
                                    <div><label class="label" for="mr-{{ $e->id }}">İptal nedeni *</label><input class="input" id="mr-{{ $e->id }}" name="reason" maxlength="200" required></div>
                                </x-dash.edit-panel>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Kayıt yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="no-print px-5 py-3">{{ $entries->links() }}</div>
    </section>
    <x-report.signatures />
</x-layout>
