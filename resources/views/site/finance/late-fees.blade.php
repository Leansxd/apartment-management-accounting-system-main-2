<x-layout heading="Gecikme Zammı" :subheading="$apartment->name.' · vadesi geçen borçlara aylık gecikme tazminatı'">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card">
            <div class="card-head"><h2 class="card-title">Ayarlar</h2></div>
            <form method="POST" action="{{ route('site.late-fees.settings', $apartment) }}" class="card-body space-y-4">
                @csrf @method('PUT')
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="late_fee_enabled" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('late_fee_enabled', $apartment->late_fee_enabled))>
                    <span><b>Gecikme zammı uygulansın.</b> <span class="text-slate-500">Kapalıyken hiçbir zam hesaplanmaz.</span></span>
                </label>
                <div><label class="label" for="rate">Aylık oran (%)</label><input class="input" id="rate" type="number" step="0.01" min="0" max="100" name="late_fee_rate" value="{{ old('late_fee_rate', $apartment->late_fee_rate) }}" required>
                    <p class="mt-1 text-[11px] text-slate-400">Kat Mülkiyeti Kanunu m.20: aylık %5.</p></div>
                <div><label class="label" for="grace">Ek süre (gün)</label><input class="input" id="grace" type="number" min="0" max="365" name="late_fee_grace_days" value="{{ old('late_fee_grace_days', $apartment->late_fee_grace_days) }}" required>
                    <p class="mt-1 text-[11px] text-slate-400">Vadeden sonra bu kadar gün zam işletilmez.</p></div>
                <button class="btn btn-primary w-full">Kaydet</button>
            </form>
        </section>

        <section class="card lg:col-span-2">
            <div class="card-head">
                <h2 class="card-title">Yazılacak gecikme zammı</h2>
                <form method="GET" class="flex items-end gap-2">
                    <div><label class="label" for="as_of">Hesap tarihi</label><input class="input" id="as_of" type="date" name="as_of" value="{{ $asOf->toDateString() }}" max="{{ today()->toDateString() }}"></div>
                    <button class="btn btn-secondary btn-sm">Önizle</button>
                </form>
            </div>
            <div class="card-body space-y-3 text-sm">
                <p>Önizlenen toplam: <b class="text-rose-600">{{ tl($total) }}</b> · <b>{{ $byUnit->count() }}</b> daire.
                    Her tam gecikme ayı için, o anki kalan borcun %{{ number_format((float) $apartment->late_fee_rate, 2, ',', '.') }}'i hesaplanır. Aynı ay için ikinci kez zam yazılmaz.</p>
                @unless ($apartment->late_fee_enabled)<p class="rounded-sm border border-amber-100 bg-amber-50 px-3 py-2 text-amber-700">Gecikme zammı kapalı. Önce ayarlardan açın.</p>@endunless
                <form method="POST" action="{{ route('site.late-fees.run', $apartment) }}" onsubmit="return confirm('Gecikme zammı borçları yazılsın mı?')">
                    @csrf<input type="hidden" name="as_of" value="{{ $asOf->toDateString() }}">
                    <button class="btn btn-primary" @disabled($byUnit->isEmpty())>Hesapla ve borç olarak yaz</button>
                </form>
                <p class="text-xs text-slate-400">Şimdiye kadar yazılan: {{ $existing['count'] }} kalem, {{ tl($existing['sum']) }}</p>
            </div>
            <div class="overflow-x-auto border-t border-slate-50">
                <table class="table">
                    <thead><tr><th>Daire</th><th class="num">Yazılacak kalem</th><th class="num">Tutar</th></tr></thead>
                    <tbody>
                    @forelse ($byUnit as $row)
                        <tr><td class="font-medium">{{ $row['unit']->label() }}</td><td class="num">{{ $row['count'] }}</td><td class="num font-semibold text-rose-600">{{ tl($row['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-slate-500">Yazılacak gecikme zammı yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layout>
