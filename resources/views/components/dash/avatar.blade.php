@props(['index' => 0, 'size' => 44])
@php
    $idx = ((int) $index) % 4;
@endphp
@if ($idx === 0)
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" class="shrink-0 rounded-xl bg-[#e8e4dc]" aria-hidden="true">
    <rect width="64" height="64" rx="12" fill="#e6e1d8"/>
    <path d="M12 64c2-13 11-20 20-20s18 7 20 20H12z" fill="#2d3142"/>
    <path d="M24 44h16l-3 10H27l-3-10z" fill="#f5f3ef"/>
    <rect x="27" y="34" width="10" height="12" rx="5" fill="#e0b194"/>
    <path d="M17 26c0-10 7-16 15-16s15 6 15 16c0 8-3 15-6 18H23c-3-3-6-10-6-18z" fill="#3a2318"/>
    <ellipse cx="32" cy="27" rx="11" ry="13" fill="#ecc1a6"/>
    <path d="M20 23c2-7 8-11 14-11 5 0 9 3 11 8-4-2-8-3-13-2-5 1-9 3-12 5z" fill="#3a2318"/>
    <circle cx="28" cy="26" r="1.4" fill="#231815"/>
    <circle cx="36" cy="26" r="1.4" fill="#231815"/>
    <path d="M29 33c2 1.5 4 1.5 6 0" stroke="#b86b58" stroke-width="1.6" stroke-linecap="round"/>
</svg>
@elseif ($idx === 1)
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" class="shrink-0 rounded-xl bg-[#dde3ea]" aria-hidden="true">
    <rect width="64" height="64" rx="12" fill="#dce2e9"/>
    <path d="M10 64c3-14 12-20 22-20s19 6 22 20H10z" fill="#f7f8fa"/>
    <path d="M25 44l7 12 7-12" fill="#5b6c85"/>
    <rect x="27" y="34" width="10" height="12" rx="5" fill="#d49e7d"/>
    <ellipse cx="32" cy="26" rx="11.5" ry="13.5" fill="#e2ae8e"/>
    <path d="M20 22c0-8 5-13 12-13s12 5 12 13c-2-4-6-6-12-6s-10 2-12 6z" fill="#221a17"/>
    <circle cx="28" cy="26" r="1.4" fill="#1e1918"/>
    <circle cx="36" cy="26" r="1.4" fill="#1e1918"/>
    <path d="M29 33c2 1.2 4 1.2 6 0" stroke="#9e5d47" stroke-width="1.6" stroke-linecap="round"/>
</svg>
@elseif ($idx === 2)
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" class="shrink-0 rounded-xl bg-[#e4dfe8]" aria-hidden="true">
    <rect width="64" height="64" rx="12" fill="#e4dfe8"/>
    <path d="M12 64c2-13 11-20 20-20s18 7 20 20H12z" fill="#4f6d7a"/>
    <rect x="27" y="34" width="10" height="12" rx="5" fill="#d9a182"/>
    <path d="M18 27c0-10 6-17 14-17s14 7 14 17c0 7-2 14-5 17H23c-3-3-5-10-5-17z" fill="#1e181a"/>
    <ellipse cx="32" cy="27" rx="11" ry="13" fill="#e6b295"/>
    <path d="M20 24c2-7 7-11 13-11 6 0 10 4 11 9-4-3-8-4-13-3-5 1-8 3-11 5z" fill="#1e181a"/>
    <circle cx="28" cy="26" r="1.4" fill="#1e181a"/>
    <circle cx="36" cy="26" r="1.4" fill="#1e181a"/>
    <path d="M29 33c2 1.5 4 1.5 6 0" stroke="#b05c4d" stroke-width="1.6" stroke-linecap="round"/>
</svg>
@else
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" fill="none" class="shrink-0 rounded-xl bg-[#dfe6e2]" aria-hidden="true">
    <rect width="64" height="64" rx="12" fill="#dfe6e2"/>
    <path d="M11 64c3-13 11-20 21-20s18 7 21 20H11z" fill="#23293d"/>
    <rect x="27" y="34" width="10" height="12" rx="5" fill="#cfa082"/>
    <ellipse cx="32" cy="26" rx="11.5" ry="13.5" fill="#ddb092"/>
    <path d="M20 21c1-7 6-12 12-12s11 5 12 12c-3-3-7-5-12-5s-9 2-12 5z" fill="#2f241f"/>
    <circle cx="28" cy="26" r="1.4" fill="#1e1918"/>
    <circle cx="36" cy="26" r="1.4" fill="#1e1918"/>
    <path d="M29 33c2 1.2 4 1.2 6 0" stroke="#945643" stroke-width="1.6" stroke-linecap="round"/>
</svg>
@endif

