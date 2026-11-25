<x-layout heading="Üye Geçmişi" :subheading="$apartment->name.' · daireden ayrılan / devredilen üyeler'">
    <x-slot:actions><a class="btn btn-secondary" href="{{ route('site.members.index', $apartment) }}">← Üyeler</a></x-slot:actions>
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Ad Soyad</th><th>Tür</th><th>Giriş</th><th>Ayrılış</th><th>Neden</th><th class="no-print"></th></tr></thead>
                <tbody>
                @forelse ($members as $m)
                    <tr>
                        <td class="font-medium"><x-unit-link :unit="$m->unit" /></td>
                        <td><x-member-link :member="$m" /><span class="block text-xs text-slate-400">{{ $m->phone }}</span></td>
                        <td>{{ \App\Models\Member::TYPES[$m->type] ?? $m->type }}</td>
                        <td>{{ $m->moved_in_on?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $m->moved_out_on?->format('d.m.Y') ?? $m->deleted_at->format('d.m.Y') }}</td>
                        <td class="text-xs">{{ $m->move_out_reason ?: '—' }}</td>
                        <td class="no-print whitespace-nowrap text-right">
                            <form method="POST" action="{{ route('site.members.restore', [$apartment, $m]) }}" class="inline" onsubmit="return confirm(@js($m->full_name.' listeye geri alınsın mı?'))">
                                @csrf<button class="btn btn-secondary btn-sm" title="Yanlışlıkla geçmişe taşındıysa listeye geri döndürür">Geri al</button>
                            </form>
                            @unless (in_array($m->id, $payerIds, true))
                                <form method="POST" action="{{ route('site.members.purge', [$apartment, $m]) }}" class="inline" onsubmit="return confirm(@js($m->full_name.' kaydı kalıcı olarak silinsin mi? Bu işlem geri alınamaz.'))">
                                    @csrf @method('DELETE')<button class="btn btn-danger btn-sm">Kalıcı sil</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">Geçmiş üye yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $members->links() }}</div>
    </section>
</x-layout>
