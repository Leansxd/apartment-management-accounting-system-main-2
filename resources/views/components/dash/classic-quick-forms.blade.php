<div class="flex gap-4 border-b border-[var(--ch-line)]">
    <button type="button" data-filter="debt" class="tab-btn active">Borçlandır</button>
    <button type="button" data-filter="collect" class="tab-btn">Tahsilat</button>
    <button type="button" data-filter="cash" class="tab-btn">Kasa</button>
    <button type="button" data-filter="transfer" class="tab-btn">Transfer</button>
</div>

<form method="POST" action="{{ route('site.debts.store', $apartment) }}" data-kind="debt" class="mt-4 space-y-3">
    @csrf
    <input type="hidden" name="target" value="unit">
    <div><label class="label" for="hud-debt-unit">Site Üyesi / Daire</label>
        <select id="hud-debt-unit" name="unit_id" class="input" required>
            @foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->label() }} - {{ $unit->members->first()?->full_name ?? 'Boş' }}</option>@endforeach
        </select>
    </div>
    <div><label class="label" for="hud-debt-category">Ödeme Grubu</label>
        <select id="hud-debt-category" name="category_id" class="input">
            @foreach ($incomeCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
        </select>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="label" for="hud-debt-description">Açıklama</label><input id="hud-debt-description" name="description" maxlength="200" value="{{ now()->locale('tr')->translatedFormat('F Y') }} Aidat" class="input" required></div>
        <div><label class="label" for="hud-debt-amount">Tutar (₺)</label><input id="hud-debt-amount" type="number" step="0.01" min="0.01" name="amount" class="input" required></div>
    </div>
    <div><label class="label" for="hud-debt-due">Son Ödeme Tarihi</label><input id="hud-debt-due" type="date" name="due_date" value="{{ today()->endOfMonth()->toDateString() }}" class="input" required></div>
    <button class="ch-btn-solid">Borçlandırmayı Tanımla</button>
</form>

<form method="POST" action="{{ route('site.collections.store', $apartment) }}" data-kind="collect" class="mt-4 space-y-3" hidden>
    @csrf
    <div><label class="label" for="hud-collect-unit">Site Üyesi / Daire</label>
        <select id="hud-collect-unit" name="unit_id" class="input" required>
            @foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->label() }} - {{ $unit->members->first()?->full_name ?? 'Boş' }}</option>@endforeach
        </select>
    </div>
    <div><label class="label" for="hud-collect-box">Kasa / Banka</label>
        <select id="hud-collect-box" name="cashbox_id" class="input" required>
            @foreach ($cashboxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach
        </select>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="label" for="hud-collect-method">Yöntem</label>
            <select id="hud-collect-method" name="method" class="input" required>
                <option value="collection">Tahsilat</option>
                <option value="pos">POS</option>
                <option value="advance">Avans</option>
            </select>
        </div>
        <div><label class="label" for="hud-collect-amount">Tutar (₺)</label><input id="hud-collect-amount" type="number" step="0.01" min="0.01" name="amount" class="input" required></div>
    </div>
    <div><label class="label" for="hud-collect-description">Açıklama</label><input id="hud-collect-description" name="description" maxlength="200" class="input"></div>
    <button class="ch-btn-solid">Tahsilatı Kaydet</button>
</form>

<form method="POST" action="{{ route('site.transactions.store', $apartment) }}" data-kind="cash" class="mt-4 space-y-3" hidden>
    @csrf
    <div><label class="label" for="hud-cash-box">Kasa / Banka</label>
        <select id="hud-cash-box" name="cashbox_id" class="input" required>
            @foreach ($cashboxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach
        </select>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div><label class="label" for="hud-cash-type">Tür</label>
            <select id="hud-cash-type" name="type" class="input" required>
                <option value="income">Gelir</option>
                <option value="expense">Gider</option>
            </select>
        </div>
        <div><label class="label" for="hud-cash-amount">Tutar (₺)</label><input id="hud-cash-amount" type="number" step="0.01" min="0.01" name="amount" class="input" required></div>
    </div>
    <div><label class="label" for="hud-cash-description">Açıklama</label><input id="hud-cash-description" name="description" maxlength="200" class="input" required></div>
    <button class="ch-btn-solid">Kasa Hareketini Kaydet</button>
</form>

<form method="POST" action="{{ route('site.transfers.store', $apartment) }}" data-kind="transfer" class="mt-4 space-y-3" hidden>
    @csrf
    <div class="grid grid-cols-2 gap-2">
        <div><label class="label" for="hud-transfer-from">Çıkan Hesap</label>
            <select id="hud-transfer-from" name="from_id" class="input" required>
                @foreach ($cashboxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach
            </select>
        </div>
        <div><label class="label" for="hud-transfer-to">Giren Hesap</label>
            <select id="hud-transfer-to" name="to_id" class="input" required>
                @foreach ($cashboxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach
            </select>
        </div>
    </div>
    <div><label class="label" for="hud-transfer-amount">Tutar (₺)</label><input id="hud-transfer-amount" type="number" step="0.01" min="0.01" name="amount" class="input" required></div>
    <div><label class="label" for="hud-transfer-description">Açıklama</label><input id="hud-transfer-description" name="description" maxlength="200" class="input"></div>
    <button class="ch-btn-solid">Virmanı Yap</button>
</form>
