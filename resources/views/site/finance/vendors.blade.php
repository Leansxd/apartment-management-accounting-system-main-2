<x-layout heading="Firmalar / Tedarikçiler" :subheading="$apartment->name">
    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Firma ekle</span></summary>
        <form method="POST" action="{{ route('site.vendors.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            @include('site.finance._vendor-fields', ['vendor' => null])
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </details>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Firma</th><th>Vergi</th><th>İletişim</th><th class="num">Ödenen toplam</th><th></th></tr></thead>
                <tbody>
                @forelse ($vendors as $v)
                    <tr>
                        <td class="font-medium">{{ $v->name }}<span class="block text-xs text-slate-400">{{ $v->address }}</span></td>
                        <td class="text-xs">{{ $v->tax_office }} {{ $v->tax_number }}</td>
                        <td class="text-xs">{{ $v->phone }}<br>{{ $v->email }}</td>
                        <td class="num">{{ tl($v->paid_total) }}</td>
                        <td class="text-right whitespace-nowrap">
                            <details class="inline-block text-left relative">
                                <summary class="btn btn-secondary btn-sm">Düzenle</summary>
                                <form method="POST" action="{{ route('site.vendors.update', [$apartment, $v]) }}" class="absolute right-0 z-10 mt-1 w-[32rem] max-w-[90vw] card card-body space-y-3 shadow-lg">
                                    @csrf @method('PUT')
                                    @include('site.finance._vendor-fields', ['vendor' => $v])
                                    <button class="btn btn-primary">Kaydet</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('site.vendors.destroy', [$apartment, $v]) }}" class="inline" onsubmit="return confirm('Firma silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Firma yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
