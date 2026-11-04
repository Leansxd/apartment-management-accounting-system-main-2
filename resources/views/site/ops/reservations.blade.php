<x-layout heading="Ortak Alan Rezervasyonu" :subheading="$apartment->name.($pending ? ' · '.$pending.' onay bekliyor' : '')">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Rezervasyon oluştur</h2></div>
            <form method="POST" action="{{ route('site.reservations.store', $apartment) }}" class="card-body space-y-3">
                @csrf
                <div><label class="label" for="r-area">Alan</label><select class="input" id="r-area" name="common_area_id" required>@foreach ($areas->where('is_active', true) as $a)<option value="{{ $a->id }}">{{ $a->name }}@if ((float) $a->hourly_fee) — {{ tl($a->hourly_fee) }}/saat @endif</option>@endforeach</select></div>
                <div><label class="label" for="r-unit">Daire</label><select class="input" id="r-unit" name="unit_id" required>@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select></div>
                <div><label class="label" for="r-date">Tarih</label><input class="input" id="r-date" type="date" name="date" min="{{ today()->toDateString() }}" value="{{ old('date', today()->addDay()->toDateString()) }}" required></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label" for="r-start">Başlangıç</label><input class="input" id="r-start" type="time" name="start_time" value="{{ old('start_time', '10:00') }}" required></div>
                    <div><label class="label" for="r-end">Bitiş</label><input class="input" id="r-end" type="time" name="end_time" value="{{ old('end_time', '12:00') }}" required></div>
                </div>
                <div><label class="label" for="r-note">Not</label><input class="input" id="r-note" name="note" value="{{ old('note') }}"></div>
                <button class="btn btn-primary w-full" @disabled($areas->where('is_active', true)->isEmpty())>Oluştur</button>
                <p class="text-xs text-slate-500">Ücretli alanlarda ücret, onayla birlikte daire borcuna yazılır.</p>
            </form>
        </section>

        <section class="card lg:col-span-2">
            <div class="card-head"><h2 class="card-title">Rezervasyonlar</h2>
                <div class="flex flex-wrap gap-1">
                    <a class="btn btn-sm {{ ! $status ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.reservations.index', $apartment) }}">Tümü</a>
                    @foreach (\App\Models\Reservation::STATUSES as $k => $v)<a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.reservations.index', [$apartment, 'status' => $k]) }}">{{ $v }}</a>@endforeach
                </div></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tarih</th><th>Alan</th><th>Daire</th><th class="num">Ücret</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($reservations as $r)
                        <tr>
                            <td class="whitespace-nowrap">{{ $r->date->format('d.m.Y') }}<span class="block text-xs text-slate-400">{{ $r->start_time }}–{{ $r->end_time }}</span></td>
                            <td>{{ $r->area->name }}@if ($r->user)<span class="block text-xs text-slate-400">{{ $r->user->name }}</span>@endif</td>
                            <td>{{ $r->unit->label() }}</td>
                            <td class="num">{{ (float) $r->fee ? tl($r->fee) : '—' }}</td>
                            <td><span class="badge {{ ['approved' => 'badge-green', 'pending' => 'badge-amber', 'rejected' => 'badge-red', 'cancelled' => 'badge-gray'][$r->status] }}">{{ \App\Models\Reservation::STATUSES[$r->status] }}</span></td>
                            <td class="whitespace-nowrap text-right">
                                @if ($r->status === 'pending')
                                    <form method="POST" action="{{ route('site.reservations.approve', [$apartment, $r]) }}" class="inline">@csrf<button class="btn btn-primary btn-sm">Onayla</button></form>
                                    <form method="POST" action="{{ route('site.reservations.reject', [$apartment, $r]) }}" class="inline">@csrf<button class="btn btn-secondary btn-sm">Reddet</button></form>
                                @endif
                                @if (in_array($r->status, ['pending', 'approved'], true))
                                    <form method="POST" action="{{ route('site.reservations.cancel', [$apartment, $r]) }}" class="inline" onsubmit="return confirm('Rezervasyon iptal edilsin mi? Ücret borcu silinir.')">@csrf<button class="btn btn-danger btn-sm">İptal</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-500">Rezervasyon yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3">{{ $reservations->links() }}</div>
        </section>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Ortak alanlar</h2></div>
        <details class="panel border-b border-slate-50" @if($errors->any()) open @endif>
            <summary class="px-5 py-3 text-xs font-black uppercase tracking-widest text-slate-700">+ Yeni ortak alan</summary>
            <form method="POST" action="{{ route('site.areas.store', $apartment) }}" class="card-body space-y-3">
                @csrf @include('site.ops._area-fields', ['area' => null])
                <button class="btn btn-primary">Kaydet</button>
            </form>
        </details>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Alan</th><th>Saatler</th><th class="num">Saatlik ücret</th><th>Onay</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($areas as $a)
                    <tr>
                        <td class="font-medium">{{ $a->name }}<span class="block text-xs text-slate-400">{{ $a->description }} {{ $a->capacity ? '· '.$a->capacity.' kişi' : '' }}</span></td>
                        <td>{{ $a->open_time }}–{{ $a->close_time }}</td>
                        <td class="num">{{ (float) $a->hourly_fee ? tl($a->hourly_fee) : 'Ücretsiz' }}</td>
                        <td>{{ $a->requires_approval ? 'Yönetici onaylı' : 'Otomatik' }}</td>
                        <td>@if ($a->is_active)<span class="badge badge-green">Açık</span>@else<span class="badge badge-gray">Kapalı</span>@endif</td>
                        <td class="whitespace-nowrap text-right">
                            <details class="relative inline-block text-left">
                                <summary class="btn btn-secondary btn-sm">Düzenle</summary>
                                <form method="POST" action="{{ route('site.areas.update', [$apartment, $a]) }}" class="absolute right-0 z-10 mt-1 w-[34rem] max-w-[90vw] card card-body space-y-3 shadow-lg">
                                    @csrf @method('PUT') @include('site.ops._area-fields', ['area' => $a])
                                    <button class="btn btn-primary">Kaydet</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('site.areas.destroy', [$apartment, $a]) }}" class="inline" onsubmit="return confirm('Alan silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Ortak alan tanımlı değil.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
