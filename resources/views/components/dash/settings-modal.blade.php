@php
    $accents = [
        'teal' => ['Turkuaz', '#0f766e'],
        'navy' => ['Lacivert', '#1d4ed8'],
        'violet' => ['Mor', '#6d28d9'],
        'graphite' => ['Grafit', '#475569'],
        'wine' => ['Bordo', '#be123c'],
    ];
    $onOff = ['1' => 'Açık', '0' => 'Kapalı'];
    $preferences = [
        ['Arayüz', 'Panelin genel düzeni', 'hud', ['modern' => 'Modern', 'classic' => 'Klasik'], ''],
        ['Renk modu', 'Seçili arayüz için geçerlidir', 'theme', ['light' => 'Açık', 'dark' => 'Koyu'], ''],
        ['Sol menüyü sabitle', 'Menü açık kalır', 'rail', $onOff, 'hud-modern-only'],
        ['Animasyonlar', 'Sayfa geçiş ve kart efektleri', 'motion', ['on' => 'Açık', 'off' => 'Kapalı'], ''],
    ];
@endphp
<div id="settings-modal" class="no-print fixed inset-0 z-[200] hidden items-start justify-center bg-black/70 pt-16 backdrop-blur-xs sm:pt-24" role="dialog" aria-modal="true" aria-label="Görünüm ayarları">
    <div class="mx-4 w-full max-w-lg overflow-hidden rounded-[24px] border border-[#242428] bg-[#141416] shadow-[0_24px_60px_rgba(0,0,0,0.85)]">
        <div class="flex items-center justify-between border-b border-[#1f1f23] px-6 py-4">
            <div>
                <h3 class="text-[15px] font-semibold text-white">Görünüm Ayarları</h3>
                <p class="text-xs text-[#71717a]">Seçimler anında uygulanır ve bu tarayıcıda saklanır</p>
            </div>
            <button type="button" data-close="settings-modal" class="flex h-8 w-8 items-center justify-center rounded-full text-[#71717a] transition-colors hover:bg-[#1f1f23] hover:text-white" aria-label="Kapat"><x-icon name="x" :size="17" /></button>
        </div>

        <div class="max-h-[65vh] divide-y divide-[#1f1f23] overflow-y-auto px-6">
            @foreach ($preferences as [$prefLabel, $prefNote, $pref, $options, $visibility])
                <div @class(['flex flex-wrap items-center justify-between gap-3 py-4', $visibility])>
                    <div>
                        <p class="text-[13px] font-medium text-white">{{ $prefLabel }}</p>
                        <p class="text-xs text-[#71717a]">{{ $prefNote }}</p>
                    </div>
                    <div class="flex gap-1.5" role="group" aria-label="{{ $prefLabel }}">
                        @foreach ($options as $value => $optionLabel)
                            <button type="button" class="tab-pill" data-pref="{{ $pref }}" data-value="{{ $value }}">{{ $optionLabel }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="hud-classic-only py-4">
                <p class="text-[13px] font-medium text-white">Klasik tema rengi</p>
                <div class="mt-2.5 flex flex-wrap gap-1.5" role="group" aria-label="Klasik tema rengi">
                    @foreach ($accents as $accent => [$accentLabel, $accentColor])
                        <button type="button" class="tab-pill" data-pref="accent" data-value="{{ $accent }}">
                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $accentColor }}"></span>{{ $accentLabel }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="border-t border-[#1f1f23] px-6 py-3.5">
            <button type="button" class="btn btn-secondary w-full" data-start-tour>Panel tanıtımını başlat</button>
        </div>

        <div class="flex items-center justify-between border-t border-[#1f1f23] px-6 py-3.5 text-xs">
            <a href="{{ $siteRoute('site.settings') }}" class="font-medium text-[#8a8a93] hover:text-white">Site ayarları →</a>
            <a href="{{ route('users.show', $user) }}" class="font-medium text-[#8a8a93] hover:text-white">Profilim →</a>
        </div>
    </div>
</div>
