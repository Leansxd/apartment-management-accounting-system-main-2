<x-layout heading="Emlak Portföyü" subheading="Satılık ve kiralık ilanlar">
    <x-slot:actions><a class="btn btn-primary" href="{{ route('properties.create') }}">+ Yeni ilan</a></x-slot:actions>

    <form method="GET" class="flex flex-wrap gap-2">
        <label class="sr-only" for="q">Ara</label>
        <input class="input !w-auto" id="q" name="q" value="{{ request('q') }}" placeholder="Başlık veya konum">
        <label class="sr-only" for="category">Kategori</label>
        <select class="input !w-auto" id="category" name="category"><option value="">Tüm kategoriler</option>@foreach (\App\Models\Property::CATEGORIES as $c)<option @selected($category === $c)>{{ $c }}</option>@endforeach</select>
        <button class="btn btn-secondary">Filtrele</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($properties as $p)
            <article class="card overflow-hidden flex flex-col">
                @if ($p->images)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($p->images[0]) }}" alt="{{ $p->title }}" class="h-44 w-full object-cover" loading="lazy">
                @else
                    <div class="h-44 w-full bg-slate-100 flex items-center justify-center text-sm text-slate-400">Görsel yok</div>
                @endif
                <div class="card-body flex flex-1 flex-col gap-1">
                    <div class="flex justify-between gap-2"><span class="badge badge-gray">{{ $p->category }}</span><span class="badge {{ $p->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ \App\Models\Property::STATUSES[$p->status] }}</span></div>
                    <h2 class="mt-1 font-semibold text-slate-900">{{ $p->title }}</h2>
                    <p class="text-sm text-slate-500">{{ $p->location ?: '—' }}</p>
                    <p class="text-sm text-slate-600">{{ collect([$p->bedrooms ? $p->bedrooms.' oda' : null, $p->bathrooms ? $p->bathrooms.' banyo' : null, $p->area ? (float) $p->area.' m²' : null])->filter()->implode(' · ') }}</p>
                    <p class="mt-auto pt-2 text-lg font-bold text-brand-600">{{ $p->price ? tl($p->price) : 'Fiyat sorunuz' }}</p>
                    @if ($p->canBeManagedBy(auth()->user()))
                        <div class="flex gap-2 pt-2">
                            <a class="btn btn-secondary btn-sm" href="{{ route('properties.edit', $p) }}">Düzenle</a>
                            <form method="POST" action="{{ route('properties.destroy', $p) }}" onsubmit="return confirm('İlan silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">İlan yok.</p>
        @endforelse
    </div>
    {{ $properties->links() }}
</x-layout>
