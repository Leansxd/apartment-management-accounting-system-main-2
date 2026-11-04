<x-layout heading="Akıllı Borç Hatırlatıcı" :subheading="$apartment->name.' · vadesi geçmiş borcu olan daireler'">
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
                        <td class="font-medium">{{ $row['unit']->label() }}</td>
                        <td>{{ $row['unit']->members->pluck('full_name')->implode(', ') ?: '—' }}</td>
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
