<x-portal-layout title="Toplantılar" :apartment="$apartment">
    <section class="card">
        <ul class="divide-y divide-slate-100">
            @forelse ($meetings as $meeting)
                <li class="px-5 py-3 text-sm">
                    <span class="block font-medium text-slate-900">{{ $meeting->title }}</span>
                    <span class="block text-xs text-slate-500">{{ \App\Models\Meeting::TYPES[$meeting->type] }} · {{ $meeting->meets_at->format('d.m.Y H:i') }} @if ($meeting->location) · {{ $meeting->location }} @endif</span>
                    @if ($meeting->agenda)<span class="mt-1 block whitespace-pre-line text-slate-600">{{ $meeting->agenda }}</span>@endif
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Toplantı yok.</li>
            @endforelse
        </ul>
    </section>
</x-portal-layout>
