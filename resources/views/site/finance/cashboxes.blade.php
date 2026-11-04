<x-layout heading="Kasa & Banka" :subheading="$apartment->name.($apartment->allow_negative_cashbox ? ' · eksi bakiyeye izin var' : '')">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cashboxes as $c)
            <div class="stat">
                <p class="stat-label">{{ \App\Models\Cashbox::TYPES[$c->type] }}</p>
                <p class="mt-1 font-semibold text-slate-800">{{ $c->name }}</p>
                <p class="stat-value {{ $c->balance < 0 ? 'text-rose-600' : 'text-brand-600' }}">{{ tl($c->balance) }}</p>
                @if ($c->account_number)<p class="text-xs text-slate-400 mt-1">{{ $c->account_number }}</p>@endif
                <a class="mt-2 inline-block text-xs text-brand-600" href="{{ route('site.cashboxes.index', [$apartment, 'cashbox_id' => $c->id]) }}">Hareketler</a>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Gelir / gider kaydı</h2></div>
            <form method="POST" action="{{ route('site.transactions.store', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div><label class="label" for="t-type">Tür</label><select class="input" id="t-type" name="type"><option value="income">Gelir (kasaya giriş)</option><option value="expense">Gider (kasadan çıkış)</option></select></div>
                <div><label class="label" for="t-box">Kasa</label><select class="input" id="t-box" name="cashbox_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="t-cat">Kategori</label><select class="input" id="t-cat" name="category_id"><option value="">—</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->type === 'income' ? 'Gelir' : 'Gider' }}: {{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="t-amount">Tutar (₺)</label><input class="input" id="t-amount" type="number" step="0.01" min="0.01" name="amount" required></div>
                <div><label class="label" for="t-desc">Açıklama</label><input class="input" id="t-desc" name="description" required></div>
                <button class="btn btn-primary w-full">Kaydet</button>
            </form>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Virman (kasalar arası)</h2></div>
            <form method="POST" action="{{ route('site.transfers.store', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div><label class="label" for="v-from">Kaynak kasa</label><select class="input" id="v-from" name="from_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ tl($c->balance) }})</option>@endforeach</select></div>
                <div><label class="label" for="v-to">Hedef kasa</label><select class="input" id="v-to" name="to_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}" @selected($loop->index === 1)>{{ $c->name }}</option>@endforeach</select></div>
                <div><label class="label" for="v-amount">Tutar (₺)</label><input class="input" id="v-amount" type="number" step="0.01" min="0.01" name="amount" required></div>
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
                <div><label class="label" for="c-acc">IBAN / hesap no</label><input class="input" id="c-acc" name="account_number"></div>
                <div><label class="label" for="c-open">Açılış bakiyesi (₺)</label><input class="input" id="c-open" type="number" step="0.01" min="0" name="opening_balance" value="0"></div>
                <button class="btn btn-primary w-full">Oluştur</button>
            </form>
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

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Kasa hareketleri</h2>
            @if ($cashboxId)<a class="btn btn-secondary btn-sm" href="{{ route('site.cashboxes.index', $apartment) }}">Tüm kasalar</a>@endif
        </div>
        @include('site.finance._transactions', ['transactions' => $transactions, 'actions' => true])
    </section>
</x-layout>
