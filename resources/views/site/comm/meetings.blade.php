<x-layout heading="Toplantılar" :subheading="$apartment->name.' · genel kurul ve yönetim kurulu'">
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Toplantı planla</span></summary>
        <form method="POST" action="{{ route('site.meetings.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div class="sm:col-span-2"><label class="label" for="title">Başlık *</label><input class="input" id="title" name="title" value="{{ old('title', now()->year.' Olağan Genel Kurul Toplantısı') }}" required></div>
                <div><label class="label" for="type">Tür</label><select class="input" id="type" name="type">@foreach (\App\Models\Meeting::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div><label class="label" for="meets_at">Tarih ve saat *</label><input class="input" id="meets_at" type="datetime-local" name="meets_at" value="{{ old('meets_at', now()->addWeeks(2)->setTime(19, 0)->format('Y-m-d\TH:i')) }}" required></div>
                <div class="sm:col-span-2"><label class="label" for="location">Yer</label><input class="input" id="location" name="location" value="{{ old('location') }}"></div>
                <div class="sm:col-span-2"><label class="label" for="agenda">Gündem</label><textarea class="input" id="agenda" name="agenda" rows="3" placeholder="1. Açılış ve başkanlık divanı seçimi&#10;2. Yıllık gelir-gider raporu&#10;3. Bütçe ve aidat tutarı">{{ old('agenda') }}</textarea></div>
            </div>
            <button class="btn btn-primary">Oluştur</button>
        </form>
    </details>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Toplantı</th><th>Tür</th><th>Yer</th><th class="num">Katılan daire</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($meetings as $m)
                    <tr>
                        <td class="whitespace-nowrap">{{ $m->meets_at->format('d.m.Y H:i') }}</td>
                        <td class="font-medium">{{ $m->title }}</td>
                        <td>{{ \App\Models\Meeting::TYPES[$m->type] }}</td>
                        <td>{{ $m->location ?: '—' }}</td>
                        <td class="num">{{ $m->attendances_count }}</td>
                        <td><span class="badge {{ ['planned' => 'badge-blue', 'held' => 'badge-green', 'cancelled' => 'badge-gray'][$m->status] }}">{{ \App\Models\Meeting::STATUSES[$m->status] }}</span></td>
                        <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('site.meetings.show', [$apartment, $m]) }}">Aç</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Toplantı yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
