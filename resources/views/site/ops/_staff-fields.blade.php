@php $p = $s ? 's'.$s->id.'-' : 's-'; @endphp
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <div><label class="label" for="{{ $p }}name">Ad soyad *</label><input class="input" id="{{ $p }}name" name="name" value="{{ $s?->name }}" required></div>
    <div><label class="label" for="{{ $p }}role">Görev</label><select class="input" id="{{ $p }}role" name="role">@foreach (\App\Models\Staff::ROLES as $r)<option @selected($s?->role === $r)>{{ $r }}</option>@endforeach</select></div>
    <div><label class="label" for="{{ $p }}status">Durum</label><select class="input" id="{{ $p }}status" name="status">@foreach (['Aktif', 'İzinde', 'Ayrıldı'] as $o)<option @selected(($s?->status ?? 'Aktif') === $o)>{{ $o }}</option>@endforeach</select></div>
    <div><label class="label" for="{{ $p }}phone">Telefon</label><input class="input" id="{{ $p }}phone" name="phone" value="{{ $s?->phone }}"></div>
    <div><label class="label" for="{{ $p }}email">E-posta</label><input class="input" id="{{ $p }}email" type="email" name="email" value="{{ $s?->email }}"></div>
    <div><label class="label" for="{{ $p }}salary">Maaş (₺)</label><input class="input" id="{{ $p }}salary" type="number" step="0.01" min="0" name="salary" value="{{ $s?->salary }}"></div>
    <div><label class="label" for="{{ $p }}start">Başlangıç</label><input class="input" id="{{ $p }}start" type="date" name="start_date" value="{{ $s?->start_date?->toDateString() }}"></div>
</div>
