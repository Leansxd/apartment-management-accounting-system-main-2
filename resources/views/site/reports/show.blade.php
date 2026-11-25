<x-layout :heading="$title" :subheading="$apartment->name">
    <x-slot:actions>
        <a class="btn btn-secondary" href="{{ route('site.reports.index', $apartment) }}">← Raporlar</a>
        <a class="btn btn-secondary" href="{{ request()->fullUrlWithQuery(['format' => 'xlsx']) }}"><x-icon name="download" :size="15" />Excel'e aktar</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Yazdır / PDF</button>
    </x-slot:actions>

    <form method="GET" class="no-print card card-body flex flex-wrap items-end gap-3">
        @unless (in_array($type, \App\Services\ModuleReportService::WITHOUT_BLOCK, true))
        <div><label class="label" for="block_id">Blok</label>
            <select class="input" id="block_id" name="block_id"><option value="">Tümü</option>@foreach ($blocks as $b)<option value="{{ $b->id }}" @selected($blockId === $b->id)>{{ $b->name }}</option>@endforeach</select></div>
        @endunless
        @if (in_array($type, \App\Http\Controllers\ReportController::DATED, true))
            <div><label class="label" for="from">Başlangıç</label><input class="input" type="date" id="from" name="from" value="{{ $from }}"></div>
            <div><label class="label" for="to">Bitiş</label><input class="input" type="date" id="to" name="to" value="{{ $to }}"></div>
        @endif
        <button class="btn btn-secondary">Uygula</button>
    </form>

    <section class="card {{ count($columns) > 7 ? 'print-landscape' : '' }}">
        <x-report.print-header :apartment="$apartment" :title="$title" :from="$from" :to="$to" :document-no="$documentNo"
            :note="$blockId ? ($blocks->firstWhere('id', $blockId)?->name.' Blok') : null" />
        <div class="card-body no-print border-b border-slate-100 flex flex-wrap justify-between gap-3">
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
                        <td class="{{ is_float($row[$key] ?? null) ? 'num' : '' }} {{ $key === 'signature' ? 'w-40 h-10' : '' }}">@php $linked = $row['_links'][$key] ?? null; @endphp @if ($linked instanceof \App\Models\Unit) <x-unit-link :unit="$linked" /> @elseif ($linked instanceof \App\Models\Member) <x-member-link :member="$linked" /> @else {{ \App\Http\Controllers\ReportController::cell($row[$key] ?? null) }} @endif</td>
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
    <x-report.signatures :roles="$type === 'hazirun' ? ['Toplantı Başkanı', 'Yazman', 'Oy Toplayıcı'] : ['Düzenleyen', 'Yönetici', 'Denetim Kurulu']" />
</x-layout>
