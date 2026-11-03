<x-layout :heading="$title" :subheading="$apartment->name">
    <x-slot:actions>
        <a class="btn btn-secondary" href="{{ route('site.reports.index', $apartment) }}">← Raporlar</a>
        <a class="btn btn-secondary" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}">Excel (CSV)</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır / PDF</button>
    </x-slot:actions>

    <form method="GET" class="no-print card card-body flex flex-wrap items-end gap-3">
        <div><label class="label" for="block_id">Blok</label>
            <select class="input" id="block_id" name="block_id"><option value="">Tümü</option>@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected($blockId === $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        @if (in_array($type, ['debts', 'overdue', 'receipts'], true))
            <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
            <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
        @endif
        <button class="btn btn-secondary">Uygula</button>
    </form>

    <section class="card">
        <div class="card-body border-b border-slate-100 flex flex-wrap justify-between gap-3">
            <div><p class="text-lg font-bold">{{ $apartment->name }}</p><p class="text-sm text-slate-500">{{ $apartment->address }}</p></div>
            <div class="text-right text-sm">
                <p class="font-semibold uppercase">{{ $title }}</p>
                <p class="text-slate-500">Belge no: {{ $documentNo }} · {{ now()->format('d.m.Y H:i') }}</p>
                @if ($from || $to)<p class="text-slate-500">{{ $from ? \Carbon\Carbon::parse($from)->format('d.m.Y') : '…' }} – {{ $to ? \Carbon\Carbon::parse($to)->format('d.m.Y') : '…' }}</p>@endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>@foreach ($columns as $key => $label)
                        <td class="{{ is_float($row[$key] ?? null) ? 'num' : '' }} {{ $key === 'signature' ? 'w-40 h-10' : '' }}">{{ \App\Http\Controllers\ReportController::cell($row[$key] ?? null) }}</td>
                    @endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="text-center text-slate-500">Kayıt yok.</td></tr>
                @endforelse
                @if ($totals && $rows->isNotEmpty())
                    <tr class="bg-slate-50 font-semibold">@foreach ($columns as $key => $label)
                        <td class="{{ is_float($totals[$key] ?? null) ? 'num' : '' }}">{{ \App\Http\Controllers\ReportController::cell($totals[$key] ?? null) }}</td>
                    @endforeach</tr>
                @endif
                </tbody>
            </table>
        </div>
        <p class="card-body text-xs text-slate-400">{{ $rows->count() }} kayıt</p>
    </section>
</x-layout>
