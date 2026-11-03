<x-layout heading="Stok / Envanter" :subheading="$apartment->name">
    @php $low = $items->filter->isLow(); @endphp
    @if ($low->isNotEmpty())
        <div role="status" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Kritik stok seviyesinde: {{ $low->pluck('name')->implode(', ') }}
        </div>
    @endif

    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Stok kalemi ekle</span></summary>
        <form method="POST" action="{{ route('site.inventory.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            <div class="form-grid">
                <div><label class="label" for="name">Ad *</label><input class="input" id="name" name="name" required></div>
                <div><label class="label" for="category">Kategori</label><select class="input" id="category" name="category">@foreach (\App\Models\InventoryItem::CATEGORIES as $c)<option>{{ $c }}</option>@endforeach</select></div>
                <div><label class="label" for="quantity">Miktar</label><input class="input" id="quantity" type="number" min="0" name="quantity" value="0" required></div>
                <div><label class="label" for="unit">Birim</label><input class="input" id="unit" name="unit" value="Adet" required></div>
                <div><label class="label" for="min_stock">Kritik seviye</label><input class="input" id="min_stock" type="number" min="0" name="min_stock" value="5" required></div>
                <div><label class="label" for="location">Konum</label><input class="input" id="location" name="location" value="Ana Depo" required></div>
            </div>
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </details>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Ad</th><th>Kategori</th><th>Konum</th><th class="num">Miktar</th><th>Giriş / Çıkış</th><th></th></tr></thead>
                <tbody>
                @forelse ($items as $i)
                    <tr>
                        <td class="font-medium">{{ $i->name }}</td>
                        <td>{{ $i->category }}</td>
                        <td>{{ $i->location }}</td>
                        <td class="num font-semibold {{ $i->isLow() ? 'text-rose-600' : '' }}">{{ $i->quantity }} {{ $i->unit }} <span class="block text-[11px] font-normal text-slate-400">kritik: {{ $i->min_stock }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('site.inventory.adjust', [$apartment, $i]) }}" class="flex gap-1">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="d-{{ $i->id }}">Miktar değişimi</label>
                                <input class="input !w-24 !py-1" id="d-{{ $i->id }}" type="number" name="delta" placeholder="+10 / -2" required>
                                <button class="btn btn-secondary btn-sm">Uygula</button>
                            </form>
                        </td>
                        <td class="text-right"><form method="POST" action="{{ route('site.inventory.destroy', [$apartment, $i]) }}" onsubmit="return confirm('Silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Stok kalemi yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
