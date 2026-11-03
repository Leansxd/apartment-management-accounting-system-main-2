<x-layout heading="Siteler" subheading="Apartman, site ve rezidanslar">
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Yeni site oluştur</span></summary>
        <form method="POST" action="{{ route('sites.store') }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="name">Site adı *</label><input class="input" id="name" name="name" value="{{ old('name') }}" required></div>
                <div><label class="label" for="code">Site kodu * <span class="text-slate-400">(davet kodlarında kullanılır)</span></label><input class="input uppercase" id="code" name="code" value="{{ old('code') }}" required maxlength="20" placeholder="ORNEKSITE"></div>
                <div><label class="label" for="city">Şehir</label><input class="input" id="city" name="city" value="{{ old('city') }}"></div>
                <div><label class="label" for="blocks">Bloklar <span class="text-slate-400">(virgülle)</span></label><input class="input" id="blocks" name="blocks" value="{{ old('blocks', 'A') }}" placeholder="A,B,C"></div>
                <div class="sm:col-span-2 lg:col-span-4"><label class="label" for="address">Adres</label><input class="input" id="address" name="address" value="{{ old('address') }}"></div>
            </div>
            <p class="text-xs text-slate-500">Site oluşturulunca "Merkez Kasa" ve standart gelir/gider kategorileri otomatik eklenir.</p>
            <button class="btn btn-primary">Oluştur</button>
        </form>
    </details>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($apartments as $a)
            <a href="{{ route('site.dashboard', $a) }}" class="card card-body hover:border-brand-500 transition-colors">
                <p class="text-xs font-semibold text-brand-600">{{ $a->code }}</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $a->name }}</h2>
                <p class="text-sm text-slate-500">{{ $a->city ?: '—' }}</p>
                <div class="mt-4 flex gap-4 text-sm text-slate-600">
                    <span><b>{{ $a->blocks_count }}</b> blok</span>
                    <span><b>{{ $a->units_count }}</b> daire</span>
                    <span><b>{{ $a->members_count }}</b> sakin</span>
                </div>
            </a>
        @empty
            <p class="text-sm text-slate-500">Henüz site yok.</p>
        @endforelse
    </div>
</x-layout>
