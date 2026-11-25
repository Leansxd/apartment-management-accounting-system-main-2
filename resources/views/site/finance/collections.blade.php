<x-layout heading="Tahsilat" :subheading="$apartment->name">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">1. Daire seçin</h2></div>
            <form method="GET" class="card-body flex gap-2">
                <label class="sr-only" for="unit_id">Daire</label>
                <select class="input" id="unit_id" name="unit_id" data-autosubmit>
                    <option value="">Daire seçin…</option>
                    @foreach ($units as $u)<option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->label() }}</option>@endforeach
                </select>
                <noscript><button class="btn btn-secondary">Seç</button></noscript>
            </form>
            @if ($unit)
                <div class="card-body pt-0 text-sm space-y-1">
                    <p><b>Sakin:</b> <x-member-links :members="$unit->members" /></p>
                    <p><b>Açık borç:</b> <span class="text-rose-600 font-semibold">{{ tl($openDebts->sum('remaining_amount')) }}</span></p>
                    <p><b>Avans:</b> {{ tl($unit->credit_balance) }}</p>
                </div>
            @endif
        </section>

        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">2. Tahsilatı kaydedin</h2><span class="text-xs text-slate-500">Ödeme farklı kasa ve dövizlerle parçalı girilebilir; önce seçilen borç, sonra en eski vadeli borçlar kapanır, artan tutar TL avansa yazılır</span></div>
            @if ($unit)
                @php
                    $paymentRows = array_values(old('parts', [['amount' => $openDebts->sum('remaining_amount') ?: '']]));
                @endphp
                <form method="POST" action="{{ route('site.collections.store', $apartment) }}" class="card-body space-y-4">
                    @csrf
                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label" for="payer_member_id">Ödeyen</label>
                            <select class="input" id="payer_member_id" name="payer_member_id">
                                <option value="">Belirtilmedi (daire adına)</option>
                                @foreach ($unit->members as $m)<option value="{{ $m->id }}" @selected((string) old('payer_member_id') === (string) $m->id)>{{ $m->full_name }} ({{ \App\Models\Member::TYPES[$m->type] ?? $m->type }})</option>@endforeach
                            </select>
                            <p class="mt-0.5 text-[11px] text-slate-400">Ödeyen seçilirse otomatik dağıtım yalnızca onun sorumlu olduğu ve daire geneli borçlara yapılır.</p></div>
                        <div><label class="label" for="debt_id">Öncelikli borç</label>
                            <select class="input" id="debt_id" name="debt_id">
                                <option value="">En eski vadeden başla</option>
                                @foreach ($openDebts as $d)<option value="{{ $d->id }}" @selected(old('debt_id', request('debt_id')) == $d->id)>{{ $d->description }} — {{ tl($d->remaining_amount) }} ({{ $d->due_date->format('d.m.Y') }})</option>@endforeach
                            </select></div>
                    </div>

                    @if ($openDebts->isNotEmpty())
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="table">
                                <thead><tr><th>Vade / Dönem</th><th>Açık borç</th><th class="num">Kalan</th><th>Durum</th><th class="num">Bu ödemeden (₺)</th></tr></thead>
                                <tbody>@foreach ($openDebts as $d)<tr>
                                    <td class="whitespace-nowrap">{{ $d->due_date->format('d.m.Y') }}@if ($d->period)<span class="block text-xs text-slate-500">{{ \Carbon\Carbon::createFromFormat('Y-m', $d->period)->locale('tr')->translatedFormat('F Y') }}</span>@endif</td>
                                    <td class="min-w-48">{{ $d->description }}<span class="block text-xs text-slate-500">{{ \App\Models\Debt::RESPONSIBLES[$d->responsible] ?? 'Daire geneli' }}</span></td>
                                    <td class="num">{{ tl($d->remaining_amount) }}</td>
                                    <td><x-debt-status :debt="$d" /></td>
                                    <td class="num whitespace-nowrap"><input class="input !w-28 !py-1 text-right text-sm" type="number" step="0.01" min="0" max="{{ $d->remaining_amount }}" name="allocations[{{ $d->id }}]" value="{{ old('allocations.'.$d->id) }}" placeholder="otomatik" aria-label="{{ $d->due_date->format('d.m.Y') }} vadeli {{ $d->description }} için ödenecek tutar" data-allocation>
                                        <button type="button" class="btn btn-secondary btn-sm ml-1" data-fill-allocation="{{ $d->remaining_amount }}">Tamamı</button></td>
                                </tr>@endforeach</tbody>
                            </table>
                            <p class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-400">Hangi vadenin borcunu kapatmak istiyorsanız tutarını girin veya "Tamamı"na basın; bu borçlar önce kapanır. Kalan ödeme otomatik dağıtılır, artan kısım avansa yazılır.</p>
                        </div>
                    @endif

                    <div class="space-y-3" id="payment-parts" data-rates='@json($rateSuggestions)' data-open="{{ $openDebts->sum('remaining_amount') }}">
                        @foreach ($paymentRows as $index => $row)
                            @include('site.finance._payment-part', ['index' => $index, 'row' => $row])
                        @endforeach
                    </div>
                    <template id="payment-part-template">@include('site.finance._payment-part', ['index' => '__INDEX__', 'row' => []])</template>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" class="btn btn-secondary btn-sm" id="add-payment-part">+ Ödeme parçası ekle</button>
                        <p class="text-xs text-slate-500">Döviz, seçilen kasanın para birimidir. Yeni döviz kasasını Kasa & Banka ekranından açabilirsiniz.</p>
                    </div>

                    <dl class="grid gap-3 rounded-xl border border-slate-200 p-3 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-slate-500">Toplam TL karşılığı</dt><dd class="font-semibold tabular-nums" id="payment-total">—</dd></div>
                        <div><dt class="text-xs text-slate-500">Açık borç</dt><dd class="font-semibold tabular-nums">{{ tl($openDebts->sum('remaining_amount')) }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Kalan borç</dt><dd class="font-semibold tabular-nums text-rose-600" id="payment-remaining">—</dd></div>
                        <div><dt class="text-xs text-slate-500">Avansa yazılacak</dt><dd class="font-semibold tabular-nums" id="payment-advance">—</dd></div>
                    </dl>

                    <div><label class="label" for="description">Açıklama</label><input class="input" id="description" name="description" value="{{ old('description') }}" maxlength="200" placeholder="{{ $unit->label() }} Tahsilat"></div>
                    <button class="btn btn-primary">Tahsilatı kaydet</button>
                </form>
                <script>
                    (function () {
                        const parts = document.getElementById('payment-parts');
                        const template = document.getElementById('payment-part-template');
                        const rates = JSON.parse(parts.dataset.rates || '{}');
                        const openDebt = parseFloat(parts.dataset.open) || 0;
                        const number = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const tl = (value) => number.format(value) + ' ₺';
                        let nextIndex = parts.children.length;

                        const currencyOf = (row) => row.querySelector('[data-part-cashbox]').selectedOptions[0]?.dataset.currency || 'TRY';
                        const rateOf = (row) => currencyOf(row) === 'TRY' ? 1 : parseFloat(row.querySelector('[data-part-rate]').value) || 0;
                        const tryOf = (row) => Math.round((parseFloat(row.querySelector('[data-part-amount]').value) || 0) * 100 * rateOf(row)) / 100;

                        function summarize() {
                            const rows = [...parts.children];
                            const total = rows.reduce((sum, row) => sum + tryOf(row), 0);
                            rows.forEach((row) => {
                                row.querySelector('[data-part-try]').textContent = tl(tryOf(row));
                                row.querySelector('[data-remove-part]').hidden = rows.length === 1;
                            });
                            document.getElementById('payment-total').textContent = tl(total);
                            document.getElementById('payment-remaining').textContent = tl(Math.max(openDebt - total, 0));
                            document.getElementById('payment-advance').textContent = tl(Math.max(total - openDebt, 0));
                        }

                        function syncCurrency(row, keepRate) {
                            const currency = currencyOf(row);
                            const isForeign = currency !== 'TRY';
                            const rate = row.querySelector('[data-part-rate]');
                            const suggestion = rates[currency];
                            row.querySelector('[data-part-currency]').textContent = currency;
                            row.querySelector('[data-part-rate-box]').hidden = !isForeign;
                            rate.disabled = !isForeign;
                            rate.required = isForeign;
                            if (!isForeign) {
                                rate.value = '';
                            } else if (!keepRate || !rate.value) {
                                rate.value = suggestion ? suggestion.rate : '';
                            }
                            row.querySelector('[data-part-rate-hint]').textContent = !isForeign ? '' : suggestion
                                ? 'Önerilen kur: ' + suggestion.source
                                : 'Kur önerisi alınamadı, kuru elle girin.';
                        }

                        parts.addEventListener('change', (event) => {
                            if (event.target.matches('[data-part-cashbox]')) {
                                syncCurrency(event.target.closest('[data-payment-part]'), false);
                                summarize();
                            }
                        });
                        parts.addEventListener('input', summarize);
                        parts.addEventListener('click', (event) => {
                            if (event.target.matches('[data-remove-part]')) {
                                event.target.closest('[data-payment-part]').remove();
                                summarize();
                            }
                        });

                        document.getElementById('add-payment-part').addEventListener('click', () => {
                            const total = [...parts.children].reduce((sum, row) => sum + tryOf(row), 0);
                            const wrapper = document.createElement('div');
                            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', nextIndex++);
                            const row = wrapper.firstElementChild;
                            parts.appendChild(row);
                            syncCurrency(row, false);
                            const remaining = Math.max(openDebt - total, 0);
                            const rate = rateOf(row);
                            row.querySelector('[data-part-amount]').value = remaining > 0 && rate > 0 ? (remaining / rate).toFixed(2) : '';
                            summarize();
                        });

                        // "Tamamı": borcun kalanını kutuya yazar; ödeme toplamı seçilenlerden azsa ilk ödeme parçasını büyütür
                        document.querySelectorAll('[data-fill-allocation]').forEach((button) => button.addEventListener('click', () => {
                            button.previousElementSibling.value = parseFloat(button.dataset.fillAllocation).toFixed(2);
                            const selected = [...document.querySelectorAll('[data-allocation]')].reduce((sum, input) => sum + (parseFloat(input.value) || 0), 0);
                            const total = [...parts.children].reduce((sum, row) => sum + tryOf(row), 0);
                            const first = parts.firstElementChild;
                            if (first && selected > total && rateOf(first) > 0) {
                                const amount = first.querySelector('[data-part-amount]');
                                amount.value = ((parseFloat(amount.value) || 0) + (selected - total) / rateOf(first)).toFixed(2);
                            }
                            summarize();
                        }));

                        [...parts.children].forEach((row) => syncCurrency(row, true));
                        summarize();
                    })();
                </script>
            @else
                <p class="card-body text-sm text-slate-500">Tahsilat almak için önce daire seçin.</p>
            @endif
        </section>
    </div>

    <x-dash.filters :fields="[['name' => 'from', 'label' => 'Başlangıç', 'type' => 'date'], ['name' => 'to', 'label' => 'Bitiş', 'type' => 'date'], ['name' => 'cashbox_id', 'label' => 'Kasa', 'type' => 'select', 'options' => $cashboxes->pluck('name', 'id')->all()], ['name' => 'method', 'label' => 'Yöntem', 'type' => 'select', 'options' => array_intersect_key(\App\Models\CashTransaction::SUB_TYPES, array_flip(['collection', 'pos', 'advance']))]]" :keep="['unit_id']" />

    <section class="card">
        <div class="card-head"><h2 class="card-title">Son tahsilatlar</h2><a class="text-xs text-brand-600" href="{{ route('site.reports.show', [$apartment, 'receipts']) }}">Makbuz listesi</a></div>
        @include('site.finance._transactions', ['transactions' => $recent, 'actions' => true])
    </section>
</x-layout>
