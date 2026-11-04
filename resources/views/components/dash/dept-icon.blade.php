@props(['index' => 0])
@php $idx = ((int) $index) % 3; @endphp
<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#f7f7fa]">
@if ($idx === 0)
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 21s-7-4.5-9.2-9C1.1 8.5 3.2 4.5 7 4.5c2.1 0 3.8 1.2 5 2.8 1.2-1.6 2.9-2.8 5-2.8 3.8 0 5.9 4 4.2 7.5-2.2 4.5-9.2 9-9.2 9z" fill="#e14942"/>
        <path d="M9.5 4.8V2.5M14.5 4.8V2.5" stroke="#b9322c" stroke-width="2.2" stroke-linecap="round"/>
        <path d="M8 9.5c1-1.5 2.5-2 4-1.5" stroke="#ff9a95" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
@elseif ($idx === 1)
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6 18L18 6M8.5 6.5l9 9" stroke="#2f8f83" stroke-width="2.2" stroke-linecap="round"/>
        <circle cx="6" cy="18" r="2.5" stroke="#2f8f83" stroke-width="2"/>
        <circle cx="18" cy="18" r="2.5" stroke="#2f8f83" stroke-width="2"/>
        <path d="M14 5l5 5-7 7-5-5 7-7z" fill="#63c5b8" fill-opacity="0.35"/>
    </svg>
@else
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3l7 3v5c0 5-3.2 8.8-7 10-3.8-1.2-7-5-7-10V6l7-3z" fill="#6e5ce0" fill-opacity="0.2" stroke="#6e5ce0" stroke-width="2"/>
        <path d="M9 12l2 2 4-4" stroke="#6e5ce0" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
@endif
</span>

