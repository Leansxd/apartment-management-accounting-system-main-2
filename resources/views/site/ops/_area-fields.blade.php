@php $p = $area ? 'ar'.$area->id.'-' : 'ar-'; @endphp
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <div><label class="label" for="{{ $p }}name">Alan adı *</label><input class="input" id="{{ $p }}name" name="name" value="{{ $area?->name }}" required placeholder="ör. Tenis kortu"></div>
    <div><label class="label" for="{{ $p }}desc">Açıklama</label><input class="input" id="{{ $p }}desc" name="description" value="{{ $area?->description }}"></div>
    <div><label class="label" for="{{ $p }}cap">Kapasite (kişi)</label><input class="input" id="{{ $p }}cap" type="number" min="1" name="capacity" value="{{ $area?->capacity }}"></div>
    <div><label class="label" for="{{ $p }}fee">Saatlik ücret (₺)</label><input class="input" id="{{ $p }}fee" type="number" step="0.01" min="0" name="hourly_fee" value="{{ $area?->hourly_fee ?? 0 }}"></div>
    <div><label class="label" for="{{ $p }}open">Açılış</label><input class="input" id="{{ $p }}open" type="time" name="open_time" value="{{ $area?->open_time ?? '08:00' }}" required></div>
    <div><label class="label" for="{{ $p }}close">Kapanış</label><input class="input" id="{{ $p }}close" type="time" name="close_time" value="{{ $area?->close_time ?? '22:00' }}" required></div>
</div>
<div class="flex flex-wrap gap-4 text-sm">
    <label class="flex items-center gap-2"><input type="checkbox" name="requires_approval" value="1" class="rounded border-slate-300" @checked($area?->requires_approval ?? true)> Yönetici onayı gerekir</label>
    <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked($area?->is_active ?? true)> Rezervasyona açık</label>
</div>
