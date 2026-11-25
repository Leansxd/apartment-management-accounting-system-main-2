{{-- Rapor filtre çubuğu. range: başlangıç / bitiş tarihi ve hızlı aralıklar; asOf: tek tarih ("itibarıyla"); slot: rapora özel alanlar --}}
@props(['range' => false, 'from' => null, 'to' => null, 'asOf' => null, 'asOfLabel' => 'Tarih', 'keep' => []])
@php
    $today = \Carbon\CarbonImmutable::today();
    $quick = $range ? [
        'Bu ay' => [$today->startOfMonth(), $today],
        'Geçen ay' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
        'Bu yıl' => [$today->startOfYear(), $today],
        'Geçen yıl' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
    ] : [];
@endphp
<form method="GET" class="no-print card card-body !py-4 flex flex-wrap items-end gap-3" data-report-toolbar>
    @foreach ($keep as $name => $value)
        @if (filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
    @endforeach
    {{ $slot }}
    @if ($range)
        <div><label class="label" for="report-from">Başlangıç</label><input class="input" type="date" id="report-from" name="from" value="{{ $from }}"></div>
        <div><label class="label" for="report-to">Bitiş</label><input class="input" type="date" id="report-to" name="to" value="{{ $to }}"></div>
    @endif
    @if ($asOf !== null)
        <div><label class="label" for="report-as-of">{{ $asOfLabel }}</label><input class="input" type="date" id="report-as-of" name="as_of" value="{{ $asOf }}"></div>
    @endif
    <button class="btn btn-secondary h-[38px]">Göster</button>
    @if ($quick)
        <div class="flex flex-wrap items-center gap-1 lg:ml-auto">
            @foreach ($quick as $label => [$start, $end])
                @php $isActive = $from === $start->toDateString() && $to === $end->toDateString(); @endphp
                <a class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-secondary' }}" href="{{ request()->fullUrlWithQuery(['from' => $start->toDateString(), 'to' => $end->toDateString(), 'page' => null, 'format' => null]) }}" @if ($isActive) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
    @endif
</form>
