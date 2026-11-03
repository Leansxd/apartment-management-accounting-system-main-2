@php $p = $vendor ? 'v'.$vendor->id.'-' : 'v-'; @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <div><label class="label" for="{{ $p }}name">Firma adı *</label><input class="input" id="{{ $p }}name" name="name" value="{{ $vendor?->name }}" required></div>
    <div><label class="label" for="{{ $p }}phone">Telefon</label><input class="input" id="{{ $p }}phone" name="phone" value="{{ $vendor?->phone }}"></div>
    <div><label class="label" for="{{ $p }}tax_office">Vergi dairesi</label><input class="input" id="{{ $p }}tax_office" name="tax_office" value="{{ $vendor?->tax_office }}"></div>
    <div><label class="label" for="{{ $p }}tax_number">Vergi no</label><input class="input" id="{{ $p }}tax_number" name="tax_number" value="{{ $vendor?->tax_number }}"></div>
    <div><label class="label" for="{{ $p }}email">E-posta</label><input class="input" id="{{ $p }}email" type="email" name="email" value="{{ $vendor?->email }}"></div>
    <div><label class="label" for="{{ $p }}address">Adres</label><input class="input" id="{{ $p }}address" name="address" value="{{ $vendor?->address }}"></div>
</div>
