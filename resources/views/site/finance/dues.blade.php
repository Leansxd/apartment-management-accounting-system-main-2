<x-layout heading="Aidat ve Borçlandırma Motoru" subheading="Tanımlar her dönem için hedef dairelere otomatik borç üretir">
    <section class="card border-brand-100">
        <div class="card-head"><h2 class="card-title">Dönem borçlarını üret</h2><span class="text-xs text-slate-500">Aynı dönemi tekrar çalıştırmak mükerrer borç yazmaz</span></div>
        <form method="POST" action="{{ route('site.dues.run', $apartment) }}" class="card-body flex flex-wrap items-end gap-3">
            @csrf
            <div><label class="label" for="period">Dönem</label><input class="input" id="period" type="month" name="period" value="{{ old('period', now()->format('Y-m')) }}" required></div>
            <button class="btn btn-primary" @disabled($definitions->where('is_active', true)->isEmpty())>Aktif tanımları çalıştır</button>
            <p class="text-xs text-slate-500">{{ $definitions->where('is_active', true)->count() }} aktif tanım · {{ $unitCount }} daire</p>
        </form>
    </section>

    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Yeni tanım</span></summary>
        <form method="POST" action="{{ route('site.dues.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="name">Ad *</label><input class="input" id="name" name="name" value="{{ old('name') }}" required placeholder="ör. Aylık Aidat"></div>
                <div><label class="label" for="calculation">Hesaplama</label><select class="input" id="calculation" name="calculation"><option value="fixed">Sabit tutar (daire başı)</option><option value="sqm">m² başına</option></select></div>
                <div><label class="label" for="amount">Tutar (₺) *</label><input class="input" id="amount" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required></div>
                <div><label class="label" for="due_day">Son ödeme günü</label><input class="input" id="due_day" type="number" min="1" max="31" name="due_day" value="{{ old('due_day', 31) }}"><p class="text-[11px] text-slate-400 mt-0.5">31 = ayın son günü</p></div>
                <div><label class="label" for="target_type">Hedef</label>
                    <select class="input" id="target_type" name="target_type" data-show-target><option value="all">Tüm site</option><option value="block">Blok</option><option value="unit">Tek daire</option></select></div>
                <div data-target-for="block" hidden><label class="label" for="block_id">Blok</label><select class="input" id="block_id" name="block_id">@foreach ($blocks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
                <div data-target-for="unit" hidden><label class="label" for="unit_id">Daire</label><select class="input" id="unit_id" name="unit_id">@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select></div>
                <div><label class="label" for="category_id">Kategori</label><select class="input" id="category_id" name="category_id"><option value="">Genel</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
            </div>
            <button class="btn btn-primary">Tanımı kaydet</button>
        </form>
    </details>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ad</th><th>Hesaplama</th><th class="num">Tutar</th><th>Hedef</th><th>Vade günü</th><th>Son çalıştırma</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($definitions as $d)
                    <tr>
                        <td class="font-medium">{{ $d->name }} <span class="text-xs text-slate-400">{{ $d->category?->name }}</span></td>
                        <td>{{ $d->calculation === 'sqm' ? 'm² başına' : 'Sabit' }}</td>
                        <td class="num">{{ tl($d->amount) }}</td>
                        <td>@switch($d->target_type)
                                @case('block') {{ $blocks->firstWhere('id', $d->target_id)?->name }} Blok @break
                                @case('unit') {{ $units->firstWhere('id', $d->target_id)?->label() }} @break
                                @default Tüm site
                            @endswitch</td>
                        <td>{{ $d->due_day >= 31 ? 'Ay sonu' : $d->due_day }}</td>
                        <td>{{ $d->last_run_period ?? '—' }}</td>
                        <td>@if ($d->is_active)<span class="badge badge-green">Aktif</span>@else<span class="badge badge-gray">Durduruldu</span>@endif</td>
                        <td class="text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('site.dues.toggle', [$apartment, $d]) }}" class="inline">@csrf @method('PATCH')<button class="btn btn-secondary btn-sm">{{ $d->is_active ? 'Durdur' : 'Aktifleştir' }}</button></form>
                            <form method="POST" action="{{ route('site.dues.destroy', [$apartment, $d]) }}" class="inline" onsubmit="return confirm('Tanım silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Tanım yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
