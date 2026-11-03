<x-layout heading="Anket & Oylama" :subheading="$apartment->name.' · sakinler mobil uygulamadan daire başına bir oy kullanır'">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Yeni anket</h2></div>
        <form method="POST" action="{{ route('site.polls.store', $apartment) }}" class="card-body space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2"><label class="label" for="question">Soru *</label><input class="input" id="question" name="question" value="{{ old('question') }}" required></div>
                <div><label class="label" for="due_date">Bitiş tarihi</label><input class="input" id="due_date" type="date" name="due_date" value="{{ old('due_date') }}"></div>
            </div>
            <div><label class="label" for="options">Seçenekler * <span class="text-slate-400">(her satıra bir tane, 2–10 adet)</span></label><textarea class="input" id="options" name="options" rows="4" required>{{ old('options', "Evet\nHayır") }}</textarea></div>
            <button class="btn btn-primary">Anketi başlat</button>
        </form>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($polls as $poll)
            <article class="card card-body space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $poll->question }}</h2>
                        <p class="text-xs text-slate-500">{{ $poll->votes_count }} / {{ $unitCount }} daire oy kullandı @if($poll->due_date)· bitiş {{ $poll->due_date->format('d.m.Y') }}@endif</p>
                    </div>
                    <span class="badge {{ $poll->isOpen() ? 'badge-green' : 'badge-gray' }}">{{ $poll->isOpen() ? 'Açık' : 'Kapalı' }}</span>
                </div>
                @foreach ($poll->options as $o)
                    @php $pct = $poll->votes_count ? round($o->votes_count / $poll->votes_count * 100) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm"><span>{{ $o->text }}</span><span class="tabular-nums text-slate-500">{{ $o->votes_count }} oy · %{{ $pct }}</span></div>
                        <div class="mt-1 h-2 rounded bg-slate-100"><div class="h-2 rounded bg-brand-500" style="width: {{ $pct }}%"></div></div>
                    </div>
                @endforeach
                <div class="flex gap-1 pt-1">
                    <form method="POST" action="{{ route('site.polls.toggle', [$apartment, $poll]) }}">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $poll->is_active ? 'Kapat' : 'Yeniden aç' }}</button></form>
                    <form method="POST" action="{{ route('site.polls.destroy', [$apartment, $poll]) }}" onsubmit="return confirm('Anket ve oylar silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500">Anket yok.</p>
        @endforelse
    </div>
</x-layout>
