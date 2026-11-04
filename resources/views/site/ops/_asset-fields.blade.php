@php $p = $asset ? 'a'.$asset->id.'-' : 'a-'; @endphp
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <div><label class="label" for="{{ $p }}name">Ad *</label><input class="input" id="{{ $p }}name" name="name" value="{{ $asset?->name }}" required></div>
    <div><label class="label" for="{{ $p }}category">Kategori</label><select class="input" id="{{ $p }}category" name="category">@foreach (\App\Models\Asset::CATEGORIES as $c)<option @selected($asset?->category === $c)>{{ $c }}</option>@endforeach</select></div>
    <div><label class="label" for="{{ $p }}vendor">Satıcı firma</label><select class="input" id="{{ $p }}vendor" name="vendor_id"><option value="">—</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" @selected($asset?->vendor_id === $v->id)>{{ $v->name }}</option>@endforeach</select></div>
    <div><label class="label" for="{{ $p }}date">Alış tarihi *</label><input class="input" id="{{ $p }}date" type="date" name="purchase_date" value="{{ $asset?->purchase_date->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
    <div><label class="label" for="{{ $p }}cost">Maliyet (₺) *</label><input class="input" id="{{ $p }}cost" type="number" step="0.01" min="0" name="cost" value="{{ $asset?->cost }}" required></div>
    <div><label class="label" for="{{ $p }}salvage">Hurda değeri (₺)</label><input class="input" id="{{ $p }}salvage" type="number" step="0.01" min="0" name="salvage_value" value="{{ $asset?->salvage_value ?? 0 }}"></div>
    <div><label class="label" for="{{ $p }}life">Faydalı ömür (yıl)</label><input class="input" id="{{ $p }}life" type="number" min="1" max="50" name="useful_life_years" value="{{ $asset?->useful_life_years ?? 5 }}" required></div>
    <div><label class="label" for="{{ $p }}serial">Seri no</label><input class="input" id="{{ $p }}serial" name="serial_no" value="{{ $asset?->serial_no }}"></div>
    <div><label class="label" for="{{ $p }}loc">Konum</label><input class="input" id="{{ $p }}loc" name="location" value="{{ $asset?->location }}"></div>
</div>
