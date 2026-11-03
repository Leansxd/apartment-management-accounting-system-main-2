<x-layout heading="Görevler" :subheading="$apartment->name">
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Görev ata</span></summary>
        <form method="POST" action="{{ route('site.tasks.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div class="sm:col-span-2"><label class="label" for="title">Başlık *</label><input class="input" id="title" name="title" value="{{ old('title') }}" required></div>
                <div><label class="label" for="staff_id">Sorumlu</label><select class="input" id="staff_id" name="staff_id"><option value="">—</option>@foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->role }})</option>@endforeach</select></div>
                <div><label class="label" for="due_date">Son tarih</label><input class="input" id="due_date" type="date" name="due_date" value="{{ old('due_date') }}"></div>
                <div><label class="label" for="priority">Öncelik</label><select class="input" id="priority" name="priority">@foreach (\App\Models\Task::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected($k === 'medium')>{{ $v }}</option>@endforeach</select></div>
                <div><label class="label" for="category">Kategori</label><select class="input" id="category" name="category">@foreach (\App\Models\Task::CATEGORIES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><label class="label" for="description">Açıklama</label><input class="input" id="description" name="description" value="{{ old('description') }}"></div>
            </div>
            <button class="btn btn-primary">Görevi oluştur</button>
        </form>
    </details>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Görev listesi</h2>
            <div class="flex gap-1">
                <a class="btn btn-sm {{ ! $status ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.tasks.index', $apartment) }}">Tümü</a>
                @foreach (\App\Models\Task::STATUSES as $k => $v)<a class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.tasks.index', [$apartment, 'status' => $k]) }}">{{ $v }}</a>@endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Görev</th><th>Sorumlu</th><th>Öncelik</th><th>Son tarih</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($tasks as $t)
                    <tr>
                        <td><span class="font-medium">{{ $t->title }}</span> <span class="badge badge-gray">{{ \App\Models\Task::CATEGORIES[$t->category] ?? $t->category }}</span>
                            @if ($t->description)<span class="block text-xs text-slate-500">{{ $t->description }}</span>@endif
                            @if ($t->notes)<span class="block text-xs text-slate-400">Not: {{ $t->notes }}</span>@endif</td>
                        <td>{{ $t->staff?->name ?? '—' }}</td>
                        <td><span class="badge {{ ['high' => 'badge-red', 'medium' => 'badge-amber', 'low' => 'badge-gray'][$t->priority] ?? 'badge-gray' }}">{{ \App\Models\Task::PRIORITIES[$t->priority] ?? $t->priority }}</span></td>
                        <td class="whitespace-nowrap {{ $t->due_date && $t->due_date->isPast() && $t->status !== 'completed' ? 'text-rose-600 font-medium' : '' }}">{{ $t->due_date?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('site.tasks.update', [$apartment, $t]) }}" class="flex gap-1">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="ts-{{ $t->id }}">Durum</label>
                                <select class="input !w-auto !py-1 text-xs" id="ts-{{ $t->id }}" name="status" onchange="this.form.submit()">@foreach (\App\Models\Task::STATUSES as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach</select>
                                <noscript><button class="btn btn-secondary btn-sm">Kaydet</button></noscript>
                            </form>
                        </td>
                        <td class="text-right"><form method="POST" action="{{ route('site.tasks.destroy', [$apartment, $t]) }}" onsubmit="return confirm('Görev silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Görev yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $tasks->links() }}</div>
    </section>
</x-layout>
