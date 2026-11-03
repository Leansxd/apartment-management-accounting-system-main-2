<x-layout heading="Duyurular" :subheading="$apartment->name.' · aktif duyurular mobil uygulamada görünür'">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Yeni duyuru</h2></div>
        <form method="POST" action="{{ route('site.announcements.store', $apartment) }}" class="card-body space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="sm:col-span-2"><label class="label" for="title">Başlık *</label><input class="input" id="title" name="title" value="{{ old('title') }}" required></div>
                <div><label class="label" for="priority">Öncelik</label><select class="input" id="priority" name="priority"><option value="normal">Normal</option><option value="high">Önemli</option></select></div>
                <div><label class="label" for="expires_on">Yayın bitişi</label><input class="input" id="expires_on" type="date" name="expires_on" value="{{ old('expires_on') }}"></div>
            </div>
            <div><label class="label" for="content">İçerik *</label><textarea class="input" id="content" name="content" rows="4" required>{{ old('content') }}</textarea></div>
            <button class="btn btn-primary">Yayınla</button>
        </form>
    </section>

    <div class="space-y-3">
        @forelse ($announcements as $a)
            <article class="card card-body {{ $a->is_active ? '' : 'opacity-60' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $a->title }} @if ($a->priority === 'high')<span class="badge badge-red">Önemli</span>@endif</h2>
                        <p class="text-xs text-slate-500">{{ $a->created_at->format('d.m.Y H:i') }} @if($a->expires_on)· {{ $a->expires_on->format('d.m.Y') }} tarihine kadar @endif · {{ $a->is_active ? 'Yayında' : 'Yayında değil' }}</p>
                    </div>
                    <div class="flex gap-1">
                        <form method="POST" action="{{ route('site.announcements.toggle', [$apartment, $a]) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $a->is_active ? 'Yayından kaldır' : 'Yayına al' }}</button></form>
                        <form method="POST" action="{{ route('site.announcements.destroy', [$apartment, $a]) }}" onsubmit="return confirm('Duyuru silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                    </div>
                </div>
                <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ $a->content }}</p>
            </article>
        @empty
            <p class="text-sm text-slate-500">Duyuru yok.</p>
        @endforelse
    </div>
    {{ $announcements->links() }}
</x-layout>
