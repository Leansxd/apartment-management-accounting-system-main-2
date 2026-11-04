<x-layout heading="Demirbaş Kaydı" :subheading="$apartment->name.' · doğrusal amortisman'">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Alış değeri</p><p class="stat-value">{{ tl($cost) }}</p></div>
        <div class="stat"><p class="stat-label">Birikmiş amortisman</p><p class="stat-value text-rose-600">{{ tl($depreciation) }}</p></div>
        <div class="stat"><p class="stat-label">Net defter değeri</p><p class="stat-value text-brand-500">{{ tl($book) }}</p></div>
    </div>

    <details class="panel card" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Demirbaş ekle</span></summary>
        <form method="POST" action="{{ route('site.assets.store', $apartment) }}" class="card-body space-y-4">
            @csrf
            @include('site.ops._asset-fields', ['asset' => null])
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </details>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Demirbaş</th><th>Alış</th><th class="num">Maliyet</th><th>Ömür</th><th class="num">Amortisman</th><th class="num">Net değer</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($assets as $a)
                    <tr class="{{ $a->status === 'disposed' ? 'opacity-60' : '' }}">
                        <td class="font-medium">{{ $a->name }}<span class="block text-xs text-slate-400">{{ $a->category }} {{ $a->location ? '· '.$a->location : '' }} {{ $a->serial_no ? '· '.$a->serial_no : '' }}</span></td>
                        <td class="whitespace-nowrap">{{ $a->purchase_date->format('d.m.Y') }}<span class="block text-xs text-slate-400">{{ $a->vendor?->name }}</span></td>
                        <td class="num">{{ tl($a->cost) }}</td>
                        <td>{{ $a->useful_life_years }} yıl</td>
                        <td class="num">{{ tl($a->accumulatedDepreciation() / 100) }}</td>
                        <td class="num font-semibold">{{ tl($a->bookValue() / 100) }}</td>
                        <td>@if ($a->status === 'active')<span class="badge badge-green">Kullanımda</span>@else<span class="badge badge-gray">Elden çıkarıldı {{ $a->disposed_on?->format('d.m.Y') }}</span>@endif</td>
                        <td class="whitespace-nowrap text-right">
                            <details class="relative inline-block text-left">
                                <summary class="btn btn-secondary btn-sm">Düzenle</summary>
                                <form method="POST" action="{{ route('site.assets.update', [$apartment, $a]) }}" class="absolute right-0 z-10 mt-1 w-[34rem] max-w-[90vw] card card-body space-y-3 shadow-lg">
                                    @csrf @method('PUT')
                                    @include('site.ops._asset-fields', ['asset' => $a])
                                    <button class="btn btn-primary">Kaydet</button>
                                </form>
                            </details>
                            @if ($a->status === 'active')
                                <form method="POST" action="{{ route('site.assets.dispose', [$apartment, $a]) }}" class="inline" data-prompt-date="{{ today()->toDateString() }}">
                                    @csrf<input type="hidden" name="disposed_on" value="{{ today()->toDateString() }}"><button class="btn btn-secondary btn-sm" onclick="return confirm('Demirbaş bugün elden çıkarılsın mı?')">Elden çıkar</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('site.assets.destroy', [$apartment, $a]) }}" class="inline" onsubmit="return confirm('Demirbaş silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Demirbaş yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
