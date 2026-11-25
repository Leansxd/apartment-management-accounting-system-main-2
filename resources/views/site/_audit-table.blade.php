@php
    $auditValue = fn ($value) => match (true) {
        $value === null => '—',
        is_bool($value) => $value ? 'evet' : 'hayır',
        is_scalar($value) => (string) $value,
        default => json_encode($value, JSON_UNESCAPED_UNICODE),
    };
@endphp
<section class="card">
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Tarih</th><th>Kullanıcı</th>@if ($showSite ?? false)<th>Site</th>@endif<th>İşlem</th><th>Modül</th><th>Referans</th><th>Detay</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-slate-500">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                    <td>
                        <x-user-link :user="$log->user" fallback="Sistem" />
                        @if ($log->ip_address)<span class="block text-[11px] text-slate-500" title="{{ $log->user_agent }}">{{ $log->ip_address }}</span>@endif
                    </td>
                    @if ($showSite ?? false)<td>{{ $log->apartment?->name ?? '—' }}</td>@endif
                    <td><span class="badge badge-gray">{{ $log->action }}</span></td>
                    <td>{{ $log->module }}</td>
                    <td class="font-mono text-xs">{{ $log->reference }}</td>
                    <td class="max-w-xl text-xs text-slate-600">
                        @foreach ((array) $log->data as $k => $v)
                            @if ($k === 'changes' && is_array($v))
                                @foreach ($v as $field => $change)
                                    <span class="mr-2 inline-block"><b>{{ $field }}:</b> {{ $auditValue($change['old'] ?? null) }} → {{ $auditValue($change['new'] ?? null) }}</span>
                                @endforeach
                            @elseif ($k === 'values' && is_array($v))
                                <details><summary class="cursor-pointer font-medium">{{ count($v) }} alan</summary>
                                    @foreach ($v as $field => $value)
                                        <span class="mr-2 inline-block"><b>{{ $field }}:</b> {{ $auditValue($value) }}</span>
                                    @endforeach
                                </details>
                            @else
                                <span class="mr-2"><b>{{ $k }}:</b> {{ $auditValue($v) }}</span>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ ($showSite ?? false) ? 7 : 6 }}" class="text-center text-slate-500">Kayıt yok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $logs->links() }}</div>
</section>
