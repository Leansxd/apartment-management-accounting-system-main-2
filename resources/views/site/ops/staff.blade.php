<x-layout heading="Personel" :subheading="$apartment->name">
    @php
        $fields = function ($s = null) {
            $p = $s ? 's'.$s->id.'-' : 's-';
            return compact('s', 'p');
        };
    @endphp
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Personel ekle</span></summary>
        <form method="POST" action="{{ route('site.staff.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            @include('site.ops._staff-fields', ['s' => null])
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </details>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ad Soyad</th><th>Görev</th><th>İletişim</th><th>Başlangıç</th><th class="num">Maaş</th><th>Açık iş</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($staff as $s)
                    <tr>
                        <td class="font-medium">{{ $s->name }}</td>
                        <td>{{ $s->role }}</td>
                        <td class="text-xs">{{ $s->phone }}<br>{{ $s->email }}</td>
                        <td>{{ $s->start_date?->format('d.m.Y') ?? '—' }}</td>
                        <td class="num">{{ $s->salary ? tl($s->salary) : '—' }}</td>
                        <td>{{ $s->tasks_count }}</td>
                        <td><span class="badge {{ $s->status === 'Aktif' ? 'badge-green' : 'badge-gray' }}">{{ $s->status }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            <details class="inline-block text-left relative">
                                <summary class="btn btn-secondary btn-sm">Düzenle</summary>
                                <form method="POST" action="{{ route('site.staff.update', [$apartment, $s]) }}" class="absolute right-0 z-10 mt-1 w-[34rem] max-w-[90vw] card card-body space-y-3 shadow-lg">
                                    @csrf @method('PUT')
                                    @include('site.ops._staff-fields', ['s' => $s])
                                    <button class="btn btn-primary">Kaydet</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('site.staff.destroy', [$apartment, $s]) }}" class="inline" onsubmit="return confirm('Personel silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Personel yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
