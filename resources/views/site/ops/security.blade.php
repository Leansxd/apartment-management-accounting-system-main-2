<x-layout heading="Güvenlik & Kargo" :subheading="$apartment->name">
    <nav class="flex flex-wrap gap-2" aria-label="Kayıt türü">
        @foreach (\App\Models\SecurityEntry::KINDS as $k => $label)
            <a class="btn {{ $kind === $k ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('site.security.index', [$apartment, 'kind' => $k]) }}" @if($kind === $k) aria-current="page" @endif>
                {{ $label }} @if ($openCounts[$k] ?? 0)<span class="badge badge-amber">{{ $openCounts[$k] }}</span>@endif
            </a>
        @endforeach
    </nav>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Yeni kayıt — {{ \App\Models\SecurityEntry::KINDS[$kind] }}</h2></div>
        <form method="POST" action="{{ route('site.security.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <div class="form-grid">
                <div><label class="label" for="unit_no">Daire</label><input class="input" id="unit_no" name="unit_no" placeholder="ör. A-42" value="{{ old('unit_no') }}"></div>
                <div><label class="label" for="full_name">{{ ['cargo' => 'Alıcı', 'parking' => 'Araç sahibi', 'visitor' => 'Ziyaretçi'][$kind] }} *</label><input class="input" id="full_name" name="full_name" required value="{{ old('full_name') }}"></div>
                @if ($kind === 'cargo')
                    <div><label class="label" for="company">Kargo firması</label><input class="input" id="company" name="company" placeholder="Yurtiçi, Aras…" value="{{ old('company') }}"></div>
                    <div><label class="label" for="tracking_no">Takip no</label><input class="input" id="tracking_no" name="tracking_no" value="{{ old('tracking_no') }}"></div>
                @else
                    <div><label class="label" for="plate">Plaka {{ $kind === 'parking' ? '*' : '' }}</label><input class="input uppercase" id="plate" name="plate" placeholder="34 ABC 123" value="{{ old('plate') }}" @required($kind === 'parking')></div>
                @endif
                <div class="sm:col-span-2"><label class="label" for="note">Not</label><input class="input" id="note" name="note" value="{{ old('note') }}"></div>
            </div>
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Kayıtlar</h2>
            <a class="btn btn-secondary btn-sm" href="{{ route('site.security.index', [$apartment, 'kind' => $kind, 'open' => request()->boolean('open') ? null : 1]) }}">{{ request()->boolean('open') ? 'Tümünü göster' : 'Yalnızca açık olanlar' }}</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Giriş</th><th>Daire</th><th>Ad</th><th>Detay</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td class="whitespace-nowrap">{{ $e->created_at->format('d.m.Y H:i') }}@if($e->closed_at)<span class="block text-xs text-slate-400">Kapanış: {{ $e->closed_at->format('d.m H:i') }}</span>@endif</td>
                        <td>{{ $e->unit_no ?: '—' }}</td>
                        <td class="font-medium">{{ $e->full_name }}</td>
                        <td class="text-xs">{{ collect([$e->company, $e->tracking_no, $e->plate, $e->note])->filter()->implode(' · ') }}</td>
                        <td>
                            <form method="POST" action="{{ route('site.security.update', [$apartment, $e]) }}">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="st-{{ $e->id }}">Durum</label>
                                <select class="input !w-auto !py-1 text-xs" id="st-{{ $e->id }}" name="status" onchange="this.form.submit()">
                                    @foreach (\App\Models\SecurityEntry::STATUSES[$e->kind] as $k => $v)<option value="{{ $k }}" @selected($e->status === $k)>{{ $v }}</option>@endforeach
                                </select>
                                <noscript><button class="btn btn-secondary btn-sm">Kaydet</button></noscript>
                            </form>
                        </td>
                        <td class="text-right"><form method="POST" action="{{ route('site.security.destroy', [$apartment, $e]) }}" onsubmit="return confirm('Kayıt silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Kayıt yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $entries->links() }}</div>
    </section>
</x-layout>
