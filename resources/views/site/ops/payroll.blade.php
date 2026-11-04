<x-layout heading="Personel Bordrosu" :subheading="$apartment->name.' · maaş giderleri'">
    <x-slot:actions>
        <form method="GET" class="flex items-end gap-2">
            <div><label class="label" for="period">Dönem</label><input class="input" id="period" type="month" name="period" value="{{ $period }}" onchange="this.form.submit()"></div>
        </form>
        <form method="POST" action="{{ route('site.payroll.generate', $apartment) }}" class="flex items-end">
            @csrf<input type="hidden" name="period" value="{{ $period }}">
            <button class="btn btn-primary">{{ $period }} bordrosunu oluştur</button>
        </form>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat"><p class="stat-label">Oluşturulacak maaş</p><p class="stat-value">{{ tl($payable) }}</p></div>
        <div class="stat"><p class="stat-label">Bordro toplamı</p><p class="stat-value">{{ tl($total) }}</p></div>
        <div class="stat"><p class="stat-label">Ödenen</p><p class="stat-value text-emerald-600">{{ tl($paid) }}</p></div>
    </div>

    <section class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Personel</th><th>Görev</th><th class="num">Maaş</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php $s = $r['staff']; $e = $r['expense']; @endphp
                    <tr>
                        <td class="font-medium">{{ $s->name }} @if ($s->status !== 'Aktif')<span class="badge badge-gray">{{ $s->status }}</span>@endif</td>
                        <td>{{ $s->role }}</td>
                        <td class="num">{{ $s->salary ? tl($s->salary) : '—' }}</td>
                        <td>@if (! $e)<span class="badge badge-gray">Bordro yok</span>@elseif ($e->status === 'approved')<span class="badge badge-green">Ödendi</span>@else<span class="badge badge-amber">Ödeme bekliyor</span>@endif</td>
                        <td class="text-right">
                            @if ($e && $e->status === 'pending')
                                <form method="POST" action="{{ route('site.expenses.approve', [$apartment, $e]) }}" class="inline-flex gap-1">
                                    @csrf
                                    <label class="sr-only" for="pay-{{ $e->id }}">Kasa</label>
                                    <select class="input !w-auto !py-1 text-xs" id="pay-{{ $e->id }}" name="cashbox_id">@foreach ($cashboxes as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ tl($c->balance) }})</option>@endforeach</select>
                                    <button class="btn btn-primary btn-sm">Öde</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Personel yok. <a class="text-brand-600 underline" href="{{ route('site.staff.index', $apartment) }}">Personel ekleyin.</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
