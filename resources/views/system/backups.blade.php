<x-layout heading="Yedekler" subheading="Veritabanı yedekleri her gece 03:00'te otomatik alınır">
    <section class="card">
        <div class="card-head">
            <h2 class="card-title">{{ $backups->count() }} yedek</h2>
            <form method="POST" action="{{ route('system.backups.store') }}">@csrf<button class="btn btn-secondary">Şimdi yedek al</button></form>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Yedek</th><th>Tarih</th><th class="num">Boyut</th></tr></thead>
                <tbody>
                @forelse ($backups as $backup)
                    <tr>
                        <td class="font-mono text-xs">{{ $backup['name'] }}</td>
                        <td>{{ $backup['created_at']->format('d.m.Y H:i') }}</td>
                        <td class="num">{{ number_format($backup['size'] / 1048576, 2, ',', '.') }} MB</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-slate-500">Henüz yedek yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($backups->isNotEmpty())
        <section class="card">
            <div class="card-head"><h2 class="card-title">Yedeği geri yükle</h2></div>
            <form method="POST" action="{{ route('system.backups.restore') }}" class="card-body space-y-4">
                @csrf
                <p class="text-sm font-medium text-rose-600">Yedekten sonra yapılan tüm işlemler kaybolur ve herkesin oturumu kapanır. İşlemden hemen önce mevcut durumun güvenlik yedeği otomatik alınır.</p>
                <div class="form-grid">
                    <div class="sm:col-span-2"><label class="label" for="backup">Yedek *</label>
                        <select class="input" id="backup" name="backup" required>
                            @foreach ($backups as $backup)<option value="{{ $backup['name'] }}">{{ $backup['created_at']->format('d.m.Y H:i') }} · {{ $backup['name'] }}</option>@endforeach
                        </select></div>
                    <div><label class="label" for="password">Şifreniz *</label><input class="input" id="password" type="password" name="password" required autocomplete="current-password"></div>
                    <div><label class="label" for="confirm">Onay: {{ $phrase }} *</label><input class="input" id="confirm" name="confirm" required autocomplete="off"></div>
                </div>
                @error('password')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                @error('confirm')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <button class="btn btn-danger">Yedeği geri yükle</button>
            </form>
        </section>
    @endif
</x-layout>
