<x-layout heading="Alacak Yaşlandırma" :subheading="$apartment->name.' · '.$asOf->format('d.m.Y').' itibarıyla açık borçlar'">
    <x-slot:actions><x-report.actions /></x-slot:actions>

    <x-report.toolbar :as-of="$asOf->toDateString()">
        <div><label class="label" for="block_id">Blok</label>
            <select class="input" id="block_id" name="block_id"><option value="">Tümü</option>@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected(request()->integer('block_id') === $b->id)>{{ $b->name }} Blok</option>@endforeach</select></div>
        <label class="flex items-center gap-2 pb-2 text-xs text-slate-500"><input type="checkbox" name="legal" value="1" class="rounded border-slate-300" @checked(request()->boolean('legal'))>Yalnızca icradakiler</label>
    </x-report.toolbar>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ($aging['buckets'] as $key => $label)
            <div class="stat"><p class="stat-label">{{ $label }}</p><p class="stat-value text-lg {{ $key === 'd90p' && $aging['totals'][$key] > 0 ? 'text-rose-600' : '' }}">{{ tl($aging['totals'][$key]) }}</p>
                <p class="text-xs text-slate-500">%{{ $aging['totals']['total'] > 0 ? number_format($aging['totals'][$key] / $aging['totals']['total'] * 100, 1, ',', '.') : '0' }}</p></div>
        @endforeach
        <div class="stat bg-brand-50/60"><p class="stat-label">Toplam</p><p class="stat-value text-lg">{{ tl($aging['totals']['total']) }}</p><p class="text-xs text-slate-500">{{ $aging['rows']->count() }} daire</p></div>
    </div>

    <section class="card print-landscape">
        <x-report.print-header :apartment="$apartment" title="Alacak Yaşlandırma" :as-of="$asOf->toDateString()" />
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Sakin</th>@foreach ($aging['buckets'] as $label)<th class="num">{{ $label }}</th>@endforeach<th class="num">Toplam</th><th class="no-print"></th></tr></thead>
                <tbody>
                @forelse ($aging['rows'] as $r)
                    <tr>
                        <td class="font-medium whitespace-nowrap"><x-unit-link :unit="$r['unit']" />@if ($r['legal']) <span class="badge badge-red">İcra</span>@endif</td>
                        <td><x-member-link :member="$r['unit']->members->first()" /></td>
                        @foreach ($aging['buckets'] as $key => $label)<td class="num {{ $key === 'd90p' && $r['buckets'][$key] ? 'text-rose-600' : '' }}">{{ $r['buckets'][$key] ? tl($r['buckets'][$key]) : '' }}</td>@endforeach
                        <td class="num font-semibold">{{ tl($r['total']) }}</td>
                        <td class="no-print whitespace-nowrap text-right">
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.statements', [$apartment, 'unit_id' => $r['unit']->id]) }}">Ekstre</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $r['unit']->id]) }}">Tahsil et</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($aging['buckets']) + 4 }}" class="text-center text-slate-500">Açık borç yok.</td></tr>
                @endforelse
                </tbody>
                @if ($aging['rows']->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-50 font-bold"><td colspan="2">TOPLAM</td>@foreach ($aging['buckets'] as $key => $label)<td class="num">{{ tl($aging['totals'][$key]) }}</td>@endforeach<td class="num">{{ tl($aging['totals']['total']) }}</td><td class="no-print"></td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="card-body text-xs text-slate-400">Gün, borcun vadesinden seçilen tarihe kadar geçen süredir. Tutarlar borçların bugünkü kalanıdır.</p>
    </section>
    <x-report.signatures />
</x-layout>
