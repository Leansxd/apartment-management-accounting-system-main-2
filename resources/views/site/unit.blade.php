<x-layout :heading="'Daire '.$unit->label()" :subheading="$apartment->name">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $unit->id]) }}">Tahsilat al</a>
        <a class="btn btn-secondary" href="{{ route('site.statements', [$apartment, 'unit_id' => $unit->id]) }}">Hesap ekstresi</a>
        <a class="btn btn-secondary" href="{{ route('site.units.index', $apartment) }}">← Daireler</a>
    </x-slot:actions>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat"><p class="stat-label">Açık Borç</p><p class="stat-value text-rose-600">{{ tl($statement['open_debt']) }}</p></div>
        <div class="stat"><p class="stat-label">Avans</p><p class="stat-value text-emerald-600">{{ tl($statement['credit']) }}</p></div>
        <div class="stat"><p class="stat-label">Net Bakiye</p><p class="stat-value">{{ tl($statement['closing']) }}</p><p class="text-xs text-slate-500">+ borçlu / − alacaklı</p></div>
        <div class="stat"><p class="stat-label">Alan / Arsa Payı</p><p class="stat-value">{{ $unit->gross_area ?? '—' }} m²</p><p class="text-xs text-slate-500">Arsa payı: {{ $unit->land_share ?? '—' }}</p></div>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Sakinler</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ad Soyad</th><th>Tür</th><th>Telefon</th><th>Mobil</th></tr></thead>
                <tbody>
                @forelse ($unit->members as $resident)
                    <tr>
                        <td class="font-medium"><x-member-link :member="$resident" /></td>
                        <td>{{ \App\Models\Member::TYPES[$resident->type] ?? $resident->type }}</td>
                        <td>{{ $resident->phone ?: '—' }}</td>
                        <td>@if ($resident->user_id)<span class="badge badge-green">Bağlı</span>@else<span class="badge badge-gray">Bekliyor</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500">Bu dairede kayıtlı sakin yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('site._unit-debts-payments')
</x-layout>
