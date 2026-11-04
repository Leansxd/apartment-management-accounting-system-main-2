@props(['title', 'href' => null])
<section {{ $attributes->merge(['class' => 'min-w-0 py-6']) }}>
    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 class="section-title">{{ $title }}</h2>
        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @if ($href)
                <a href="{{ $href }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-muted transition-colors hover:bg-slate-50 hover:text-ink" aria-label="{{ $title }} — tümünü aç" title="Tümünü aç">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" /></svg>
                </a>
            @endif
        </div>
    </div>
    {{ $slot }}
</section>
