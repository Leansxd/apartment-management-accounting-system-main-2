<x-portal-layout title="Özet" :apartment="$apartment">
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="stat"><p class="stat-label">Açık borcum</p><p class="stat-value">{{ tl($openTotal) }}</p></div>
        <div class="stat"><p class="stat-label">Vadesi geçen</p><p class="stat-value metric-bad">{{ tl($overdueTotal) }}</p></div>
    </div>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Yaklaşan ve geciken borçlar</h2><a class="text-xs font-medium text-slate-600 hover:underline" href="{{ route('portal.debts', $apartment) }}">Tümü</a></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($nextDebts as $debt)
                <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <span class="min-w-0"><span class="block truncate font-medium text-slate-900">{{ $debt->description }}</span>
                        <span class="block text-xs text-slate-500">{{ $debt->unit->label() }} · Vade {{ $debt->due_date->format('d.m.Y') }}</span></span>
                    <span class="flex shrink-0 items-center gap-2 font-semibold tabular-nums text-slate-900">{{ tl($debt->remaining_amount) }} <x-debt-status :debt="$debt" /></span>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Açık borcunuz yok.</li>
            @endforelse
        </ul>
    </section>

    <div class="grid gap-4 md:grid-cols-2">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Duyurular</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($announcements as $announcement)
                    <li class="px-5 py-3 text-sm"><span class="block font-medium text-slate-900">{{ $announcement->title }}</span><span class="block text-xs text-slate-600">{{ \Illuminate\Support\Str::limit($announcement->content, 140) }}</span></li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Güncel duyuru yok.</li>
                @endforelse
            </ul>
        </section>
        <section class="card">
            <div class="card-head"><h2 class="card-title">Son bildirimler</h2><a class="text-xs font-medium text-slate-600 hover:underline" href="{{ route('portal.notifications', $apartment) }}">Tümü</a></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($notifications as $notification)
                    <li class="px-5 py-3 text-sm"><span class="block font-medium text-slate-900">{{ $notification->title }}</span><span class="block text-xs text-slate-500">{{ $notification->created_at->format('d.m.Y H:i') }}</span></li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Bildirim yok.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-portal-layout>
