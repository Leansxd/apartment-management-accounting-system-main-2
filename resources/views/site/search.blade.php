<x-layout heading="Arama" :subheading="$apartment->name">
    <form method="GET" class="card card-body flex gap-2">
        <label class="sr-only" for="search-q">Ara</label>
        <input class="input" id="search-q" type="search" name="q" value="{{ $term }}" placeholder="Daire, sakin, telefon, borç, talep veya gider" minlength="2" autofocus>
        <button class="btn btn-primary">Ara</button>
    </form>

    @php $total = $units->count() + $members->count() + $debts->count() + $tickets->count() + $expenses->count(); @endphp
    @if (mb_strlen($term) < 2)
        <p class="text-sm text-slate-500">Aramak için en az 2 karakter yazın.</p>
    @elseif ($total === 0)
        <p class="text-sm text-slate-500">"{{ $term }}" için sonuç bulunamadı.</p>
    @endif

    @if ($units->isNotEmpty() && auth()->user()->can('site-access', [$apartment, 'site.units.index']))
        <section class="card">
            <div class="card-head"><h2 class="card-title">Daireler ({{ $units->count() }})</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($units as $unit)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                        <span><b><x-unit-link :unit="$unit" /></b> <span class="text-slate-500">· <x-member-links :members="$unit->members" empty="Sakin yok" /></span></span>
                        <span class="flex gap-1">
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.debts.index', [$apartment, 'unit_id' => $unit->id]) }}">Borçlar</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $unit->id]) }}">Tahsilat</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($members->isNotEmpty() && auth()->user()->can('site-access', [$apartment, 'site.members.index']))
        <section class="card">
            <div class="card-head"><h2 class="card-title">Sakinler ({{ $members->count() }})</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($members as $member)
                    <li class="px-5 py-2.5"><a class="font-medium hover:underline" href="{{ route('site.members.show', [$apartment, $member]) }}">{{ $member->full_name }}</a>
                        <span class="text-slate-500">· <x-unit-link :unit="$member->unit" fallback="" /> @if ($member->phone) · {{ $member->phone }} @endif @if ($member->email) · {{ $member->email }} @endif</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($debts->isNotEmpty() && auth()->user()->can('site-access', [$apartment, 'site.debts.index']))
        <section class="card">
            <div class="card-head"><h2 class="card-title">Borçlar ({{ $debts->count() }})</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($debts as $debt)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                        <span><b><x-unit-link :unit="$debt->unit" fallback="" /></b> · <a class="hover:underline" href="{{ route('site.debts.index', [$apartment, 'unit_id' => $debt->unit_id]) }}">{{ $debt->description }}</a></span>
                        <span class="flex items-center gap-2 tabular-nums">{{ tl($debt->remaining_amount) }} <x-debt-status :debt="$debt" /></span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($tickets->isNotEmpty() && auth()->user()->can('site-access', [$apartment, 'site.tickets.index']))
        <section class="card">
            <div class="card-head"><h2 class="card-title">Talepler ({{ $tickets->count() }})</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($tickets as $ticket)
                    <li class="flex items-center justify-between gap-2 px-5 py-2.5"><a class="hover:underline" href="{{ route('site.tickets.index', [$apartment, 'q' => $ticket->subject]) }}">{{ $ticket->subject }} <span class="text-slate-500">· {{ $ticket->unit_no ?: 'Ortak alan' }}</span></a>
                        <span class="badge badge-gray">{{ \App\Models\Ticket::STATUSES[$ticket->status] }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($expenses->isNotEmpty() && auth()->user()->can('site-access', [$apartment, 'site.expenses.index']))
        <section class="card">
            <div class="card-head"><h2 class="card-title">Giderler ({{ $expenses->count() }})</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($expenses as $expense)
                    <li class="flex items-center justify-between gap-2 px-5 py-2.5"><a class="hover:underline" href="{{ route('site.expenses.index', [$apartment, 'q' => $expense->description]) }}">{{ $expense->description }}</a>
                        <span class="tabular-nums">{{ tl($expense->total_amount) }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layout>
