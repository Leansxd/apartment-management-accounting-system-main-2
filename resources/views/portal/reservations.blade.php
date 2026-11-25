<x-portal-layout title="Rezervasyon" :apartment="$apartment">
    @if ($areas->isNotEmpty())
        <section class="card">
            <div class="card-head"><h2 class="card-title">Yeni rezervasyon</h2></div>
            <form method="POST" action="{{ route('portal.reservations.store', $apartment) }}" class="card-body space-y-4">
                @csrf
                <div class="form-grid">
                    <div class="sm:col-span-2"><label class="label" for="common_area_id">Ortak alan *</label>
                        <select class="input" id="common_area_id" name="common_area_id" required>
                            @foreach ($areas as $area)<option value="{{ $area->id }}" @selected((int) old('common_area_id') === $area->id)>{{ $area->name }}@if ((float) $area->hourly_fee > 0) · saatlik {{ tl($area->hourly_fee) }}@endif</option>@endforeach
                        </select></div>
                    <div><label class="label" for="date">Tarih *</label><input class="input" id="date" type="date" name="date" min="{{ today()->toDateString() }}" value="{{ old('date') }}" required></div>
                    @if ($units->count() > 1)
                        <div><label class="label" for="unit_id">Daire</label><select class="input" id="unit_id" name="unit_id">@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->label() }}</option>@endforeach</select></div>
                    @endif
                    <div><label class="label" for="start_time">Başlangıç *</label><input class="input" id="start_time" type="time" name="start_time" value="{{ old('start_time') }}" required></div>
                    <div><label class="label" for="end_time">Bitiş *</label><input class="input" id="end_time" type="time" name="end_time" value="{{ old('end_time') }}" required></div>
                    <div class="sm:col-span-2"><label class="label" for="note">Not</label><input class="input" id="note" name="note" maxlength="200" value="{{ old('note') }}"></div>
                </div>
                <button class="btn btn-primary">Rezervasyon yap</button>
            </form>
        </section>
    @endif

    <section class="card">
        <div class="card-head"><h2 class="card-title">Rezervasyonlarım</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($reservations as $reservation)
                <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <span class="min-w-0"><span class="block font-medium text-slate-900">{{ $reservation->area->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $reservation->date->format('d.m.Y') }} · {{ $reservation->start_time }}–{{ $reservation->end_time }} @if ((float) $reservation->fee > 0) · {{ tl($reservation->fee) }} @endif</span></span>
                    <span class="flex shrink-0 items-center gap-2">
                        <span class="badge badge-gray">{{ \App\Models\Reservation::STATUSES[$reservation->status] }}</span>
                        @if ($reservation->isUpcoming())
                            <form method="POST" action="{{ route('portal.reservations.cancel', [$apartment, $reservation->id]) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">İptal</button></form>
                        @endif
                    </span>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Rezervasyon yok.</li>
            @endforelse
        </ul>
    </section>
</x-portal-layout>
