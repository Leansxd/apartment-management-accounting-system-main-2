{{-- Rapor sayfalarının sağ üst düğmeleri: biçimli Excel (.xlsx) çıktısı ve yazdırma --}}
@props(['csv' => true])
<div class="no-print flex flex-wrap items-center gap-2">
    @if ($csv)
        <a class="btn btn-secondary" href="{{ request()->fullUrlWithQuery(['format' => 'xlsx', 'page' => null]) }}"><x-icon name="download" :size="15" />Excel'e aktar</a>
    @endif
    <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" :size="15" />Yazdır / PDF</button>
</div>
