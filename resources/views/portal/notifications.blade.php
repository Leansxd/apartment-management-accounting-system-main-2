<x-portal-layout title="Bildirimler" :apartment="$apartment">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Bildirimler</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                    <span class="min-w-0">
                        <span class="block font-medium text-slate-900">{{ $notification->title }}</span>
                        <span class="block text-slate-600">{{ $notification->body }}</span>
                        <span class="block text-xs text-slate-500">{{ $notification->created_at->format('d.m.Y H:i') }}</span>
                    </span>
                    @if ($notification->readers->isEmpty())
                        <form method="POST" action="{{ route('portal.notifications.read', [$apartment, $notification->id]) }}">@csrf<button class="btn btn-secondary btn-sm">Okundu</button></form>
                    @else
                        <span class="badge badge-gray">Okundu</span>
                    @endif
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Bildirim yok.</li>
            @endforelse
        </ul>
        <div class="px-5 py-3">{{ $notifications->links() }}</div>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Duyurular</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($announcements as $announcement)
                <li class="px-5 py-3 text-sm">
                    <span class="block font-medium text-slate-900">{{ $announcement->title }}</span>
                    <span class="block whitespace-pre-line text-slate-600">{{ $announcement->content }}</span>
                    <span class="block text-xs text-slate-500">{{ $announcement->created_at->format('d.m.Y') }}</span>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Güncel duyuru yok.</li>
            @endforelse
        </ul>
    </section>
</x-portal-layout>
