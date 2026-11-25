<x-portal-layout title="Taleplerim" :apartment="$apartment">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Yeni talep</h2></div>
        <form method="POST" action="{{ route('portal.tickets.store', $apartment) }}" enctype="multipart/form-data" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div class="sm:col-span-2"><label class="label" for="subject">Konu *</label><input class="input" id="subject" name="subject" maxlength="150" value="{{ old('subject') }}" required></div>
                <div><label class="label" for="category">Kategori *</label><select class="input" id="category" name="category" required>@foreach (\App\Models\Ticket::CATEGORIES as $category)<option @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select></div>
                <div><label class="label" for="priority">Öncelik</label><select class="input" id="priority" name="priority">@foreach (\App\Models\Ticket::PRIORITIES as $key => $label)<option value="{{ $key }}" @selected(old('priority', 'normal') === $key)>{{ $label }}</option>@endforeach</select></div>
                @if ($units->count() > 1)
                    <div><label class="label" for="unit_id">Daire</label><select class="input" id="unit_id" name="unit_id">@foreach ($units as $unit)<option value="{{ $unit->id }}">{{ $unit->label() }}</option>@endforeach</select></div>
                @endif
                <div class="sm:col-span-2 lg:col-span-4"><label class="label" for="description">Açıklama</label><textarea class="input" id="description" name="description" rows="3" maxlength="3000">{{ old('description') }}</textarea></div>
                <div class="sm:col-span-2"><label class="label" for="photo">Fotoğraf</label><input class="input" id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
            </div>
            <button class="btn btn-primary">Gönder</button>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Geçmiş talepler</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($tickets as $ticket)
                <li class="px-5 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><span class="font-medium text-slate-900">{{ $ticket->subject }}</span><span class="badge badge-gray">{{ \App\Models\Ticket::STATUSES[$ticket->status] }}</span></div>
                    @if ($ticket->description)<p class="text-slate-600">{{ $ticket->description }}</p>@endif
                    @if ($ticket->response)<p class="mt-1 rounded-xl bg-slate-50 px-3 py-2 text-slate-700"><b>Yönetim:</b> {{ $ticket->response }}</p>@endif
                    <p class="text-xs text-slate-500">{{ $ticket->created_at->format('d.m.Y H:i') }}</p>
                    @include('site.ops._ticket-thread', ['t' => $ticket, 'attachmentRoute' => fn ($message) => route('portal.tickets.attachment', [$apartment, $ticket->id, $message->id])])
                    @if ($ticket->status !== 'closed')
                        <form method="POST" action="{{ route('portal.tickets.messages', [$apartment, $ticket->id]) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-end gap-2">
                            @csrf
                            <div class="min-w-48 flex-1"><label class="label" for="tm-{{ $ticket->id }}">Mesaj yaz</label><input class="input" id="tm-{{ $ticket->id }}" name="body" maxlength="3000"></div>
                            <div><label class="label" for="tp-{{ $ticket->id }}">Fotoğraf</label><input class="input" id="tp-{{ $ticket->id }}" type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
                            <button class="btn btn-secondary">Gönder</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-slate-500">Henüz talebiniz yok.</li>
            @endforelse
        </ul>
        <div class="px-5 py-3">{{ $tickets->links() }}</div>
    </section>
</x-portal-layout>
