<x-layout heading="Alacak Yaşlandırma" :subheading="$apartment->name.' · '.$asOf->format('d.m.Y').' itibarıyla açık borçlar'">
    <x-slot:actions>
        <form method="GET" class="flex items-end gap-2">
            <div><label class="label" for="as_of">Tarih</label><input class="input" type="date" id="as_of" name="as_of" value="{{ $asOf->toDateString() }}"></div>
            <button class="btn btn-secondary">Göster</button>
            <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır</button>
        </form>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($aging['buckets'] as $key => $label)
            <div class="stat"><p class="stat-label">{{ $label }}</p><p class="stat-value text-lg {{ $key === 'd90p' && $aging['totals'][$key] > 0 ? 'text-rose-600' : '' }}">{{ tl($aging['totals'][$key]) }}</p></div>
        @endforeach
        <div class="stat bg-brand-50/60"><p class="stat-label">Toplam</p><p class="stat-value text-lg">{{ tl($aging['totals']['total']) }}</p></div>
    </div>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Sakin</th>@foreach ($aging['buckets'] as $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Toplam</th></tr></thead>
                <tbody>
                @forelse ($aging['rows'] as $r)
                    <tr>
                        <td class="font-medium">{{ $r['unit']->label() }}@if ($r['legal']) <span class="badge badge-red">İcra</span>@endif</td>
                        <td>{{ $r['member'] ?? '—' }}</td>
                        @foreach ($aging['buckets'] as $key => $label)<td class="num">{{ $r['buckets'][$key] ? tl($r['buckets'][$key]) : '' }}</td>@endforeach
                        <td class="num font-semibold">{{ tl($r['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($aging['buckets']) + 3 }}" class="text-center text-slate-500">Açık borç yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
