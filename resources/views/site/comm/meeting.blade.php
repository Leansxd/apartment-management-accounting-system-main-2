<x-layout :heading="$meeting->title" :subheading="\App\Models\Meeting::TYPES[$meeting->type].' · '.$meeting->meets_at->format('d.m.Y H:i').($meeting->location ? ' · '.$meeting->location : '')">
    <x-slot:actions>
        <a class="btn btn-secondary" href="{{ route('site.meetings.index', $apartment) }}">← Toplantılar</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Hazirun cetveli yazdır</button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Katılan daire</p><p class="stat-value">{{ $quorum['count'] }} / {{ $quorum['total'] }}</p></div>
        <div class="stat"><p class="stat-label">Arsa payı</p><p class="stat-value">@if ($quorum['share_used']){{ number_format($quorum['share'], 4, ',', '.') }} / {{ number_format($quorum['total_share'], 4, ',', '.') }}@else — @endif</p></div>
        <div class="stat {{ $quorum['ok'] ? 'bg-brand-50/60' : 'bg-rose-50/60' }}"><p class="stat-label">Toplantı yeter sayısı</p>
            <p class="stat-value {{ $quorum['ok'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $quorum['ok'] ? 'Sağlandı ✓' : 'Sağlanamadı' }}</p>
            <p class="text-[11px] text-slate-500">Sayı: {{ $quorum['count_ok'] ? 'evet' : 'hayır' }} · Arsa payı: {{ $quorum['share_used'] ? ($quorum['share_ok'] ? 'evet' : 'hayır') : 'girilmemiş' }}</p></div>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Hazirun (katılım) cetveli</h2><span class="text-xs text-slate-500 no-print">Katılan daireleri işaretleyin; vekil ile gelenler için "Vekil" kutusunu kullanın.</span></div>
        <form method="POST" action="{{ route('site.meetings.attendance', [$apartment, $meeting]) }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="no-print">Katılım</th><th>Daire</th><th>Kat maliki</th><th class="num">Arsa payı</th><th>Katılımcı</th><th>Vekil</th><th class="hidden print:table-cell">İmza</th></tr></thead>
                    <tbody>
                    @foreach ($units as $u)
                        @php $a = $attendance->get($u->id); $owner = $u->members->firstWhere('type', 'owner') ?? $u->members->first(); @endphp
                        <tr>
                            <td class="no-print"><input type="checkbox" name="present[]" value="{{ $u->id }}" class="rounded border-slate-300" aria-label="{{ $u->label() }} katıldı" @checked($a)></td>
                            <td class="font-medium">{{ $u->label() }}</td>
                            <td>{{ $owner?->full_name ?? '—' }}</td>
                            <td class="num">{{ $u->land_share ? number_format($u->land_share, 4, ',', '.') : '—' }}</td>
                            <td><label class="sr-only" for="n-{{ $u->id }}">Katılımcı adı</label><input class="input !py-1 text-xs no-print" id="n-{{ $u->id }}" name="name[{{ $u->id }}]" value="{{ $a?->attendee_name }}" placeholder="{{ $owner?->full_name }}"><span class="hidden print:inline">{{ $a?->attendee_name ?: ($a ? $owner?->full_name : '') }}</span></td>
                            <td><input type="checkbox" name="proxy[{{ $u->id }}]" value="1" class="rounded border-slate-300 no-print" aria-label="{{ $u->label() }} vekil" @checked($a?->is_proxy)><span class="hidden print:inline">{{ $a?->is_proxy ? 'Vekil' : '' }}</span></td>
                            <td class="hidden h-10 w-40 print:table-cell"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body no-print border-t border-slate-50"><button class="btn btn-primary">Hazirunu kaydet</button></div>
        </form>
    </section>

    <div class="no-print grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Toplantı bilgileri</h2></div>
            <form method="POST" action="{{ route('site.meetings.update', [$apartment, $meeting]) }}" class="card-body space-y-3">
                @csrf @method('PUT')
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label" for="m-title">Başlık</label><input class="input" id="m-title" name="title" value="{{ $meeting->title }}" required></div>
                    <div><label class="label" for="m-type">Tür</label><select class="input" id="m-type" name="type">@foreach (\App\Models\Meeting::TYPES as $k => $v)<option value="{{ $k }}" @selected($meeting->type === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div><label class="label" for="m-status">Durum</label><select class="input" id="m-status" name="status">@foreach (\App\Models\Meeting::STATUSES as $k => $v)<option value="{{ $k }}" @selected($meeting->status === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div><label class="label" for="m-at">Tarih ve saat</label><input class="input" id="m-at" type="datetime-local" name="meets_at" value="{{ $meeting->meets_at->format('Y-m-d\TH:i') }}" required></div>
                    <div><label class="label" for="m-loc">Yer</label><input class="input" id="m-loc" name="location" value="{{ $meeting->location }}"></div>
                </div>
                <div><label class="label" for="m-agenda">Gündem</label><textarea class="input" id="m-agenda" name="agenda" rows="4">{{ $meeting->agenda }}</textarea></div>
                <div><label class="label" for="m-min">Toplantı tutanağı</label><textarea class="input" id="m-min" name="minutes" rows="5">{{ $meeting->minutes }}</textarea></div>
                <div class="flex gap-2"><button class="btn btn-primary">Kaydet</button></div>
            </form>
            <form method="POST" action="{{ route('site.meetings.destroy', [$apartment, $meeting]) }}" class="card-body border-t border-slate-50" onsubmit="return confirm('Toplantı silinsin mi? Kararlar korunur.')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Toplantıyı sil</button></form>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Alınan kararlar</h2></div>
            <ul class="divide-y divide-slate-50">
                @forelse ($decisions as $d)
                    <li class="px-5 py-3 text-sm"><b>Karar {{ $d->decision_no }}</b> — {{ $d->subject }}<p class="mt-1 whitespace-pre-line text-slate-600">{{ $d->content }}</p></li>
                @empty
                    <li class="px-5 py-3 text-sm text-slate-500">Henüz karar eklenmedi.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('site.meetings.decisions', [$apartment, $meeting]) }}" class="card-body space-y-3 border-t border-slate-50">
                @csrf
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label class="label" for="d-no">Karar no</label><input class="input" id="d-no" name="decision_no" value="{{ old('decision_no', $nextNo) }}" required></div>
                    <div class="sm:col-span-2"><label class="label" for="d-sub">Konu</label><input class="input" id="d-sub" name="subject" value="{{ old('subject') }}" required></div>
                </div>
                <div><label class="label" for="d-con">Karar metni</label><textarea class="input" id="d-con" name="content" rows="3" required>{{ old('content') }}</textarea></div>
                <button class="btn btn-primary">Karar ekle</button>
            </form>
        </section>
    </div>

    @if ($meeting->agenda)
        <section class="card"><div class="card-head"><h2 class="card-title">Gündem</h2></div><p class="card-body whitespace-pre-line text-sm">{{ $meeting->agenda }}</p></section>
    @endif
</x-layout>
