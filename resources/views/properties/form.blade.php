@php
    $editing = $property->exists;
    $C = \App\Http\Controllers\PropertyController::class;
@endphp
<x-layout :heading="$editing ? 'İlanı düzenle' : 'Yeni ilan'">
    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('properties.update', $property) : route('properties.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="card">
            <div class="card-head"><h2 class="card-title">Genel bilgiler</h2></div>
            <div class="card-body form-grid">
                <div class="sm:col-span-2"><label class="label" for="title">Başlık *</label><input class="input" id="title" name="title" value="{{ old('title', $property->title) }}" required></div>
                <div><label class="label" for="category">Kategori</label><select class="input" id="category" name="category">@foreach (\App\Models\Property::CATEGORIES as $c)<option @selected(old('category', $property->category) === $c)>{{ $c }}</option>@endforeach</select></div>
                <div><label class="label" for="status">Durum</label><select class="input" id="status" name="status">@foreach (\App\Models\Property::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $property->status ?? 'active') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div><label class="label" for="price">Fiyat (₺)</label><input class="input" id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $property->price) }}"></div>
                <div class="sm:col-span-2"><label class="label" for="location">Konum</label><input class="input" id="location" name="location" value="{{ old('location', $property->location) }}"></div>
                <div><label class="label" for="video_url">Video bağlantısı</label><input class="input" id="video_url" type="url" name="video_url" value="{{ old('video_url', $property->video_url) }}"></div>
                <div class="sm:col-span-2 lg:col-span-4"><label class="label" for="description">Açıklama</label><textarea class="input" id="description" name="description" rows="5">{{ old('description', $property->description) }}</textarea></div>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Özellikler</h2></div>
            <div class="card-body space-y-4">
                <div class="form-grid">
                    @foreach (['bedrooms' => 'Oda', 'bathrooms' => 'Banyo', 'balconies' => 'Balkon', 'area' => 'Alan (m²)', 'floor' => 'Bulunduğu kat', 'total_floors' => 'Toplam kat', 'year_built' => 'Yapım yılı'] as $field => $label)
                        <div><label class="label" for="{{ $field }}">{{ $label }}</label><input class="input" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $property->{$field}) }}" @if($field !== 'floor') type="number" min="0" @endif @if($field === 'area') step="0.01" @endif></div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    @foreach (['is_furnished' => 'Eşyalı', 'is_citizenship' => 'Vatandaşlığa uygun', 'is_residence_permit' => 'Oturum iznine uygun', 'is_credit_eligible' => 'Krediye uygun'] as $flag => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" name="{{ $flag }}" value="1" class="rounded border-slate-300" @checked(old($flag, $property->{$flag}))> {{ $label }}</label>
                    @endforeach
                </div>
                <fieldset>
                    <legend class="label">Olanaklar</legend>
                    <div class="flex flex-wrap gap-3 text-sm">
                        @foreach ($C::FEATURES as $f)<label class="flex items-center gap-1.5"><input type="checkbox" name="features[]" value="{{ $f }}" class="rounded border-slate-300" @checked(in_array($f, old('features', $property->features ?? []), true))> {{ $f }}</label>@endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="label">Cephe</legend>
                    <div class="flex flex-wrap gap-3 text-sm">
                        @foreach ($C::FACADES as $f)<label class="flex items-center gap-1.5"><input type="checkbox" name="facades[]" value="{{ $f }}" class="rounded border-slate-300" @checked(in_array($f, old('facades', $property->facades ?? []), true))> {{ $f }}</label>@endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="label">Mesafeler</legend>
                    <div class="form-grid">
                        @foreach ($C::DISTANCES as $d)<div><label class="label" for="dist-{{ $loop->index }}">{{ $d }}</label><input class="input" id="dist-{{ $loop->index }}" name="distances[{{ $d }}]" value="{{ old('distances.'.$d, $property->distances[$d] ?? '') }}" placeholder="ör. 500 m"></div>@endforeach
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Görseller ve danışman</h2></div>
            <div class="card-body space-y-4">
                @if ($editing && $property->images)
                    <div class="flex flex-wrap gap-3">
                        @foreach ($property->images as $img)
                            <label class="text-xs text-center">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($img) }}" alt="" class="h-24 w-32 rounded object-cover">
                                <span class="mt-1 flex items-center justify-center gap-1"><input type="checkbox" name="keep_images[]" value="{{ $img }}" checked class="rounded border-slate-300"> Koru</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <div><label class="label" for="images">Görsel yükle (en fazla 10, her biri 5 MB)</label><input class="input" id="images" type="file" name="images[]" accept="image/*" multiple></div>
                <div class="form-grid">
                    <div><label class="label" for="agent_name">Danışman</label><input class="input" id="agent_name" name="agent_name" value="{{ old('agent_name', $property->agent_name) }}"></div>
                    <div><label class="label" for="agent_phone">Danışman telefonu</label><input class="input" id="agent_phone" name="agent_phone" value="{{ old('agent_phone', $property->agent_phone) }}"></div>
                </div>
            </div>
        </section>

        <div class="flex gap-2">
            <button class="btn btn-primary">{{ $editing ? 'Kaydet' : 'İlanı oluştur' }}</button>
            <a class="btn btn-secondary" href="{{ route('properties.index') }}">Vazgeç</a>
        </div>
    </form>
</x-layout>
