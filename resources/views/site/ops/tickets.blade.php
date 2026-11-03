<x-layout heading="Destek Talepleri" :subheading="$apartment->name.' · mobil uygulamadan açılan talepler de burada görünür'">
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Talep aç</span></summary>
        <form method="POST" action="{{ route('site.tickets.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div class="sm:col-span-2"><label class="label" for="subject">Konu *</label><input class="input" id="subject" name="subject" value="{{ old('subject') }}" required></div>
                <div><label class="label" for="unit_id">Daire</label><select class="input" id="unit_id" name="unit_id"><option value="">Ortak alan</option>@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select></div>
                <div><label class="label" for="category">Kategori</label><select class="input" id="category" name="category">@foreach (\App\Models\Ticket::CATEGORIES as $c)<option>{{ $c }}</option>@endforeach</select></div>
                <div><label class="label" for="priority">Öncelik</label><select class="input" id="priority" name="priority">@foreach (\App\Models\Ticket::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'normal')>{{ $v }}</option>@endforeach</select></div>
                <div class="sm:col-span-2 lg:col-span-3"><label class="label" for="description">Açıklama</label><input class="input" id="description" name="description" value="{{ old('description') }}"></div>
            </div>
            <button class="btn btn-primary">Talep oluştur</button>
        </form>
    </details>

    <nav class="flex flex-wrap gap-2" aria-label="Durum filtresi">
        <a class="btn btn-sm {{ ! $status ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.tickets.index', $apartment) }}">Tümü ({{ $counts->sum() }})</a>
        @foreach (\App\Models\Ticket::STATUSES as $k => $v)
            <a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.tickets.index', [$apartment, 'status' => $k]) }}">{{ $v }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
    </nav>

    <div class="space-y-3">
        @forelse ($tickets as $t)
            <article class="card card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900">{{ $t->subject }}</h2>
                        <p class="text-xs text-slate-500">{{ $t->created_at->format('d.m.Y H:i') }} · {{ $t->unit_no ?: 'Ortak alan' }} · {{ $t->category }}
                            @if ($t->user) · <span class="badge badge-blue">Mobil: {{ $t->user->name }}</span>@endif</p>
                    </div>
                    <span class="badge {{ $t->priority === 'high' ? 'badge-red' : 'badge-gray' }}">{{ \App\Models\Ticket::PRIORITIES[$t->priority] ?? $t->priority }}</span>
                </div>
                @if ($t->description)<p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ $t->description }}</p>@endif
                <form method="POST" action="{{ route('site.tickets.update', [$apartment, $t]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf @method('PATCH')
                    <div><label class="label" for="ts-{{ $t->id }}">Durum</label><select class="input !w-auto" id="ts-{{ $t->id }}" name="status">@foreach (\App\Models\Ticket::STATUSES as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="flex-1 min-w-[16rem]"><label class="label" for="tr-{{ $t->id }}">Yanıt (sakin görür)</label><input class="input" id="tr-{{ $t->id }}" name="response" value="{{ $t->response }}"></div>
                    <button class="btn btn-secondary">Güncelle</button>
                </form>
            </article>
        @empty
            <p class="text-sm text-slate-500">Talep yok.</p>
        @endforelse
    </div>
    {{ $tickets->links() }}
</x-layout>
