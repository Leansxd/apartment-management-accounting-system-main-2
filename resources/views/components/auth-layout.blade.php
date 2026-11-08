@props(['title'])
<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leans | {{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .font-display { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
        .gradient-canvas {
            background-color: #f78d42;
            background-image:
                radial-gradient(at 15% 15%, #ffd29d 0px, transparent 50%),
                radial-gradient(at 85% 20%, #ff6b1a 0px, transparent 55%),
                radial-gradient(at 75% 75%, #f2550c 0px, transparent 50%),
                radial-gradient(at 20% 80%, #ffa35c 0px, transparent 50%),
                radial-gradient(at 50% 50%, #f98436 0px, transparent 60%);
        }
    </style>
</head>
<body class="min-h-screen bg-white font-sans text-slate-900 antialiased selection:bg-orange-500/20">
    @php
        $highlights = [
            ['circle-dollar-sign', 'Aidat ve tahsilat', 'Borçlar dairelere yazılır, ödemeler makbuzla kapanır.'],
            ['wallet', 'Kasa ve banka', 'Hesaplar ve ekstreler aynı ekranda izlenir.'],
            ['bar-chart-3', 'Muhasebe ve raporlar', 'Yevmiye, mizan ve bilanço kendiliğinden oluşur.'],
            ['users', 'Sakin ve saha', 'Duyuru, talep, görev ve ziyaretçi takibi tek yerde.'],
        ];
    @endphp

    <div class="grid min-h-screen w-full lg:grid-cols-12">
        <div class="flex flex-col justify-between bg-white px-8 py-10 sm:px-14 lg:col-span-5 lg:px-14 xl:px-20">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 self-start font-display text-[24px] font-extrabold tracking-tight text-slate-950">
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-950 text-white shadow-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                    </svg>
                </span>
                <span>leans<span class="text-orange-500">.</span></span>
            </a>

            <div class="mx-auto my-auto w-full max-w-[410px] py-6">
                {{ $slot }}
            </div>

            <p class="text-[12px] text-slate-400">© {{ now()->year }} Leans</p>
        </div>

        <div class="gradient-canvas relative hidden overflow-hidden lg:col-span-7 lg:flex lg:items-center lg:justify-center lg:p-10">
            <div class="w-full max-w-[480px] rounded-[32px] bg-white p-9 shadow-[0_35px_80px_-15px_rgba(120,40,0,0.25)]">
                <h2 class="font-display text-[30px] font-bold leading-tight tracking-tight text-slate-950">Binanın bütün işleri tek panelde</h2>
                <ul class="mt-7 space-y-5">
                    @foreach ($highlights as [$highlightIcon, $highlightTitle, $highlightText])
                        <li class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-orange-50 text-orange-600"><x-icon :name="$highlightIcon" :size="20" /></span>
                            <span>
                                <span class="block font-display text-[15px] font-bold text-slate-950">{{ $highlightTitle }}</span>
                                <span class="mt-0.5 block text-[13px] leading-relaxed text-slate-500">{{ $highlightText }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
