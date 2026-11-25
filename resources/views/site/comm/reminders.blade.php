<x-layout heading="Akıllı Borç Hatırlatıcı" :subheading="$apartment->name.' · vadesi geçmiş borcu olan daireler'">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <form method="POST" action="{{ route('site.reminders.settings', $apartment) }}" class="card">
        @csrf @method('PUT')
        <div class="card-head"><h2 class="card-title">Otomatik hatırlatma</h2>
            <span class="text-xs text-slate-500">Son gönderim: {{ $apartment->reminder_last_run_at?->format('d.m.Y H:i') ?? 'henüz yok' }}</span></div>
        <div class="card-body flex flex-wrap items-end gap-5">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reminder_auto_run" value="1" class="rounded border-slate-300" @checked($apartment->reminder_auto_run)> Gecikmiş dairelere otomatik hatırlat</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reminder_send_email" value="1" class="rounded border-slate-300" @checked($apartment->reminder_send_email)> E-posta da gönder</label>
            <div><label class="label" for="reminder_interval_days">Kaç günde bir</label><input class="input !w-28" id="reminder_interval_days" type="number" min="1" max="60" name="reminder_interval_days" value="{{ old('reminder_interval_days', $apartment->reminder_interval_days) }}" required></div>
            <button class="btn btn-secondary">Kaydet</button>
        </div>
    </form>

    <form method="POST" action="{{ route('site.reminders.send', $apartment) }}" class="card">
        @csrf
        <div class="card-head">
            <h2 class="card-title">{{ $overdue->count() }} daire · toplam {{ tl($overdue->sum('total')) }}</h2>
            <span class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_email" value="1" class="rounded border-slate-300"> E-posta da gönder</label>
                <button class="btn btn-primary" @disabled($overdue->isEmpty())>Seçilenlere hatırlatma gönder</button>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th><span class="sr-only">Seç</span></th><th>Daire</th><th>Sakin</th><th>Telefon</th><th class="num">Borç sayısı</th><th>En eski vade</th><th class="num">Gecikmiş tutar</th><th></th></tr></thead>
                <tbody>
                @forelse ($overdue as $row)
                    <tr>
                        <td><input type="checkbox" name="unit_ids[]" value="{{ $row['unit']->id }}" checked aria-label="{{ $row['unit']->label() }} seç" class="rounded border-slate-300"></td>
                        <td class="font-medium"><x-unit-link :unit="$row['unit']" /></td>
                        <td><x-member-links :members="$row['unit']->members" /></td>
                        <td>{{ $row['unit']->members->pluck('phone')->filter()->implode(', ') ?: '—' }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td>{{ $row['oldest'] }}</td>
                        <td class="num font-semibold text-rose-600">{{ tl($row['total']) }}</td>
                        <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('site.collections.index', [$apartment, 'unit_id' => $row['unit']->id]) }}">Tahsil et</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">Vadesi geçmiş borç yok. 🎉</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </form>
</x-layout>
