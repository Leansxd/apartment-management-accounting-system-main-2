<x-layout heading="Belge Arşivi" :subheading="$apartment->name.' · sözleşme, fatura, tutanak'">
    @if ($expiring->isNotEmpty())
        <div role="status" class="rounded-sm border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Süresi dolan / dolmak üzere olan belgeler: {{ $expiring->map(fn ($d) => $d->title.' ('.$d->expires_on->format('d.m.Y').')')->implode(', ') }}
        </div>
    @endif

    <section class="card">
        <div class="card-head"><h2 class="card-title">Belge yükle</h2><span class="text-xs text-slate-500">PDF, görsel, Word/Excel, CSV, ZIP · en çok 10 MB</span></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('site.documents.store', $apartment) }}" class="card-body space-y-3">
            @csrf
            <div class="form-grid">
                <div class="sm:col-span-2"><label class="label" for="title">Başlık *</label><input class="input" id="title" name="title" value="{{ old('title') }}" required placeholder="ör. Asansör bakım sözleşmesi"></div>
                <div><label class="label" for="category">Kategori</label><select class="input" id="category" name="category">@foreach (\App\Models\Document::CATEGORIES as $c)<option>{{ $c }}</option>@endforeach</select></div>
                <div><label class="label" for="expires_on">Bitiş / geçerlilik</label><input class="input" id="expires_on" type="date" name="expires_on" value="{{ old('expires_on') }}"></div>
                <div class="sm:col-span-2"><label class="label" for="file">Dosya *</label><input class="input" id="file" type="file" name="file" required></div>
            </div>
            <button class="btn btn-primary">Yükle</button>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Belgeler</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <label class="sr-only" for="q">Ara</label><input class="input !w-auto" id="q" name="q" value="{{ request('q') }}" placeholder="Başlık ara">
                <label class="sr-only" for="f-cat">Kategori</label><select class="input !w-auto" id="f-cat" name="category"><option value="">Tüm kategoriler</option>@foreach (\App\Models\Document::CATEGORIES as $c)<option @selected($category === $c)>{{ $c }}</option>@endforeach</select>
                <button class="btn btn-secondary">Filtrele</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Belge</th><th>Kategori</th><th>Geçerlilik</th><th>Boyut</th><th>Yükleyen</th><th></th></tr></thead>
                <tbody>
                @forelse ($documents as $d)
                    <tr>
                        <td class="font-medium">{{ $d->title }}<span class="block text-xs text-slate-400">{{ $d->original_name }}</span></td>
                        <td><span class="badge badge-gray">{{ $d->category }}</span></td>
                        <td class="whitespace-nowrap {{ $d->expires_on && $d->expires_on->isPast() ? 'font-semibold text-rose-600' : '' }}">{{ $d->expires_on?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $d->humanSize() }}</td>
                        <td class="text-xs">{{ $d->uploader?->name }}<span class="block text-slate-400">{{ $d->created_at->format('d.m.Y') }}</span></td>
                        <td class="whitespace-nowrap text-right">
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.documents.download', [$apartment, $d]) }}">İndir</a>
                            <form method="POST" action="{{ route('site.documents.destroy', [$apartment, $d]) }}" class="inline" onsubmit="return confirm('Belge silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Belge yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $documents->links() }}</div>
    </section>
</x-layout>
