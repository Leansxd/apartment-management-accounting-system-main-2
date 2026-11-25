<x-portal-layout title="Anketler" :apartment="$apartment">
    @forelse ($polls as $poll)
        <section class="card">
            <div class="card-head">
                <h2 class="card-title">{{ $poll->question }}</h2>
                <span @class(['badge', 'badge-green' => $poll->isOpen(), 'badge-gray' => ! $poll->isOpen()])>{{ $poll->isOpen() ? 'Açık' : 'Kapalı' }}</span>
            </div>
            @if ($poll->isOpen() && ! in_array($poll->id, $votedPollIds, true))
                <form method="POST" action="{{ route('portal.polls.vote', [$apartment, $poll->id]) }}" class="card-body space-y-3">
                    @csrf
                    @foreach ($poll->options as $option)
                        <label class="flex items-center gap-2 text-sm text-slate-900"><input type="radio" name="option_id" value="{{ $option->id }}" required> {{ $option->text }}</label>
                    @endforeach
                    @if ($units->count() > 1)
                        <div class="max-w-xs"><label class="label" for="unit-{{ $poll->id }}">Daire</label>
                            <select class="input" id="unit-{{ $poll->id }}" name="unit_id">@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->label() }}</option>@endforeach</select></div>
                    @endif
                    <button class="btn btn-primary">Oy ver</button>
                </form>
            @else
                <ul class="card-body space-y-1.5 text-sm text-slate-900">
                    @foreach ($poll->options as $option)
                        <li class="flex justify-between gap-3"><span>{{ $option->text }}</span><span class="font-semibold tabular-nums">{{ $option->votes_count }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <p class="text-sm text-slate-500">Anket yok.</p>
    @endforelse
</x-portal-layout>
