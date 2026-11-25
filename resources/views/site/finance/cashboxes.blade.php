<x-layout heading="Kasa & Banka" :subheading="$apartment->name.($apartment->allow_negative_cashbox ? ' · eksi bakiyeye izin var' : '')">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cashboxes as $c)
            <div class="stat">
                <p class="stat-label">{{ \App\Models\Cashbox::TYPES[$c->type] }}</p>
                <p class="mt-1 font-semibold text-slate-800">{{ $c->name }}</p>
                <p class="stat-value {{ $c->nativeBalance() < 0 ? 'text-rose-600' : 'text-brand-600' }}">{{ $c->formattedBalance() }}</p>
                @if ($c->isForeign())<p class="text-xs text-slate-500 mt-1">TL karşılığı: {{ tl($c->balance) }}</p>@endif
                @if ($c->account_number)<p class="text-xs text-slate-400 mt-1">{{ $c->account_number }}</p>@endif
                @unless ($c->is_active)<span class="badge badge-gray mt-1">Pasif</span>@endunless
                <div class="mt-2 flex flex-wrap items-center gap-1">
                    <a class="mr-auto text-xs text-brand-600" href="{{ route('site.cashboxes.index', [$apartment, 'cashbox_id' => $c->id]) }}">Hareketler</a>
                    <x-dash.edit-panel :action="route('site.cashboxes.update', [$apartment, $c])" width="w-64">
                        <div><label class="label" for="cb-n-{{ $c->id }}">Ad *</label><input class="input" id="cb-n-{{ $c->id }}" name="name" maxlength="80" value="{{ $c->name }}" required></div>
                        <div><label class="label" for="cb-a-{{ $c->id }}">Hesap no</label><input class="input" id="cb-a-{{ $c->id }}" name="account_number" maxlength="40" value="{{ $c->account_number }}"></div>
                    </x-dash.edit-panel>
                    <form method="POST" action="{{ route('site.cashboxes.toggle', [$apartment, $c]) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $c->is_active ? 'Pasife al' : 'Aktifleştir' }}</button></form>
                    <form method="POST" action="{{ route('site.cashboxes.destroy', [$apartment, $c]) }}" onsubmit="return confirm('Kasa silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                </div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Gelir / gider kaydı</h2></div>
            <form method="POST" action="{{ route('site.transactions.store', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div><label class="label" for="t-type">Tür</label><select class="input" id="t-type" name="type"><option value="income">Gelir (kasaya giriş)</option><option value="expense">Gider (kasadan çıkış)</option></select></div>
                <div><label class="label" for="t-box">Kasa</label><select class="input" id="t-box" name="cashbox_id">@foreach ($activeCashboxes as $c)<option value="{{ $c->id }}" data-currency="{{ $c->currency }}">{{ $c->labelWithCurrency() }}</option>@endforeach</select></div>
                <div><label class="label" for="t-cat">Kategori</label><select class="input" id="t-cat" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->type === 'income' ? 'Gelir' : 'Gider' }}: {{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="t-amount">Tutar (kasanın para birimi)</label><input class="input" id="t-amount" type="number" step="0.01" min="0.01" name="amount" required></div>
                <div id="t-rate-box" hidden><label class="label" for="t-rate">Kur (₺)</label><input class="input" id="t-rate" type="number" step="0.000001" min="0.000001" name="exchange_rate" disabled></div>
                <div><label class="label" for="t-desc">Açıklama</label><input class="input" id="t-desc" name="description" required></div>
                <button class="btn btn-primary w-full">Kaydet</button>
            </form>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Virman (kasalar arası)</h2></div>
            <form method="POST" action="{{ route('site.transfers.store', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div><label class="label" for="v-from">Kaynak kasa</label><select class="input" id="v-from" name="from_id">@foreach ($activeCashboxes as $c)<option value="{{ $c->id }}" data-currency="{{ $c->currency }}">{{ $c->name }} ({{ $c->formattedBalance() }})</option>@endforeach</select></div>
                <div><label class="label" for="v-to">Hedef kasa</label><select class="input" id="v-to" name="to_id">@foreach ($activeCashboxes as $c)<option value="{{ $c->id }}" data-currency="{{ $c->currency }}" @selected($loop->index === 1)>{{ $c->labelWithCurrency() }}</option>@endforeach</select></div>
                <div><label class="label" for="v-amount">Çıkan tutar (kaynak kasanın para birimi)</label><input class="input" id="v-amount" type="number" step="0.01" min="0.01" name="amount" required></div>
                <div id="v-target-box" hidden><label class="label" for="v-target">Hedef kasaya giren tutar</label><input class="input" id="v-target" type="number" step="0.01" min="0.01" name="target_amount" disabled><p class="mt-1 text-xs text-slate-500">Döviz TL'ye bozdurulursa ortalama maliyetle fark kur farkı olarak yazılır.</p></div>
                <div><label class="label" for="v-desc">Açıklama</label><input class="input" id="v-desc" name="description"></div>
                <button class="btn btn-primary w-full" @disabled($cashboxes->count() < 2)>Virman yap</button>
                @if ($cashboxes->count() < 2)<p class="text-xs text-slate-500">Virman için en az iki kasa gerekir.</p>@endif
            </form>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Yeni kasa / banka hesabı</h2></div>
            <form method="POST" action="{{ route('site.cashboxes.store', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div><label class="label" for="c-name">Ad</label><input class="input" id="c-name" name="name" required placeholder="ör. Ziraat Bankası"></div>
                <div><label class="label" for="c-type">Tür</label><select class="input" id="c-type" name="type">@foreach (\App\Models\Cashbox::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div><label class="label" for="c-currency">Para birimi</label><select class="input" id="c-currency" name="currency">@foreach (\App\Models\Cashbox::CURRENCIES as $code => $name)<option value="{{ $code }}" @selected(old('currency', 'TRY') === $code)>{{ $name }}</option>@endforeach</select></div>
                <div><label class="label" for="c-acc">IBAN / hesap no</label><input class="input" id="c-acc" name="account_number"></div>
                <div><label class="label" for="c-open">Açılış bakiyesi (kasanın para birimi)</label><input class="input" id="c-open" type="number" step="0.01" min="0" name="opening_balance" value="0"></div>
                <div id="c-rate-box" hidden><label class="label" for="c-rate">Açılış kuru (₺)</label><input class="input" id="c-rate" type="number" step="0.000001" min="0.000001" name="opening_rate" disabled></div>
                <button class="btn btn-primary w-full">Oluştur</button>
            </form>
            <div class="card-body border-t border-slate-100">
                <p class="mb-2 text-sm font-medium">Kategoriler</p>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($categories as $category)
                        <li class="flex items-center justify-between gap-2 py-1.5">
                            <span class="min-w-0 truncate">{{ $category->name }} <span class="text-xs text-slate-500">· {{ $category->type === 'income' ? 'Gelir' : 'Gider' }}</span></span>
                            <span class="flex shrink-0 gap-1">
                                <x-dash.edit-panel :action="route('site.categories.update', [$apartment, $category])" width="w-64">
                                    <div><label class="label" for="ct-{{ $category->id }}">Kategori adı *</label><input class="input" id="ct-{{ $category->id }}" name="name" maxlength="60" value="{{ $category->name }}" required></div>
                                </x-dash.edit-panel>
                                <form method="POST" action="{{ route('site.categories.destroy', [$apartment, $category]) }}" onsubmit="return confirm('Kategori silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <form method="POST" action="{{ route('site.categories.store', $apartment) }}" class="card-body border-t border-slate-100 space-y-2">
                @csrf
                <p class="text-sm font-medium">Yeni kategori</p>
                <div class="flex gap-2">
                    <label class="sr-only" for="cat-name">Kategori adı</label><input class="input" id="cat-name" name="name" required placeholder="Kategori adı">
                    <label class="sr-only" for="cat-type">Tür</label><select class="input !w-auto" id="cat-type" name="type"><option value="income">Gelir</option><option value="expense">Gider</option></select>
                </div>
                <button class="btn btn-secondary w-full">Kategori ekle</button>
            </form>
        </section>
    </div>

    <x-dash.filters :fields="[['name' => 'type', 'label' => 'Tür', 'type' => 'select', 'options' => ['income' => 'Gelir', 'expense' => 'Gider']], ['name' => 'from', 'label' => 'Başlangıç', 'type' => 'date'], ['name' => 'to', 'label' => 'Bitiş', 'type' => 'date'], ['name' => 'q', 'label' => 'Ara', 'type' => 'search', 'placeholder' => 'Açıklama']]" :keep="['cashbox_id']" />

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Kasa hareketleri</h2>
            @if ($cashboxId)<a class="btn btn-secondary btn-sm" href="{{ route('site.cashboxes.index', $apartment) }}">Tüm kasalar</a>@endif
        </div>
        @include('site.finance._transactions', ['transactions' => $transactions, 'actions' => true])
    </section>
    <script>
        (function () {
            const rates = @json($rateSuggestions);
            const byId = (id) => document.getElementById(id);
            const currencyOf = (select) => select.selectedOptions[0]?.dataset.currency || select.value || 'TRY';
            const toggle = (box, input, visible) => { box.hidden = !visible; input.disabled = !visible; input.required = visible; };

            // Yetkiye göre bazı formlar sayfada olmayabilir; her bölüm yalnızca kendi formu varsa çalışır
            function syncTransaction() {
                if (! byId('t-box')) { return; }
                const currency = currencyOf(byId('t-box'));
                toggle(byId('t-rate-box'), byId('t-rate'), currency !== 'TRY' && byId('t-type').value === 'income');
                byId('t-rate').value = rates[currency]?.rate || '';
            }
            function syncTransfer() {
                if (! byId('v-from')) { return; }
                toggle(byId('v-target-box'), byId('v-target'), byId('v-from').selectedOptions[0]?.dataset.currency !== byId('v-to').selectedOptions[0]?.dataset.currency);
            }
            function syncOpening() {
                if (! byId('c-currency')) { return; }
                toggle(byId('c-rate-box'), byId('c-rate'), byId('c-currency').value !== 'TRY');
                byId('c-rate').value = rates[byId('c-currency').value]?.rate || '';
            }

            byId('t-box')?.addEventListener('change', syncTransaction);
            byId('t-type')?.addEventListener('change', syncTransaction);
            byId('v-from')?.addEventListener('change', syncTransfer);
            byId('v-to')?.addEventListener('change', syncTransfer);
            byId('c-currency')?.addEventListener('change', syncOpening);
            syncTransaction();
            syncTransfer();
            syncOpening();
        })();
    </script>
</x-layout>
