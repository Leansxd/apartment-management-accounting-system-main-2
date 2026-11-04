<x-layout heading="Üye Geçmişi" :subheading="$apartment->name.' · daireden ayrılan / devredilen üyeler'">
    <x-slot:actions><a class="btn btn-secondary" href="{{ route('site.members.index', $apartment) }}">← Üyeler</a></x-slot:actions>
    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Daire</th><th>Ad Soyad</th><th>Tür</th><th>Giriş</th><th>Ayrılış</th><th>Neden</th></tr></thead>
                <tbody>
                @forelse ($members as $m)
                    <tr>
                        <td class="font-medium">{{ $m->unit->label() }}</td>
                        <td>{{ $m->full_name }}<span class="block text-xs text-slate-400">{{ $m->phone }}</span></td>
                        <td>{{ \App\Models\Member::TYPES[$m->type] ?? $m->type }}</td>
                        <td>{{ $m->moved_in_on?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $m->moved_out_on?->format('d.m.Y') ?? $m->deleted_at->format('d.m.Y') }}</td>
                        <td class="text-xs">{{ $m->move_out_reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Geçmiş üye yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $members->links() }}</div>
    </section>
</x-layout>
