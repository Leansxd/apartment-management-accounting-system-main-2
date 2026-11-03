<x-layout heading="İşlem Kayıtları" subheading="Finansal ve yönetimsel işlemlerin denetim izi (silinemez)">
    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>İşlem</th><th>Modül</th><th>Referans</th><th>Detay</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td><span class="badge badge-gray">{{ $log->action }}</span></td>
                        <td>{{ $log->module }}</td>
                        <td class="font-mono text-xs">{{ $log->reference }}</td>
                        <td class="text-xs text-slate-600">
                            @foreach ((array) $log->data as $k => $v)
                                <span class="mr-2"><b>{{ $k }}:</b> {{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</span>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Kayıt yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $logs->links() }}</div>
    </section>
</x-layout>
