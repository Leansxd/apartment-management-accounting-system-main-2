<x-layout heading="Bildirim Gönder" :subheading="$apartment->name.' · bildirimler mobil uygulamanın bildirim kutusunda görünür'">
    <section class="card">
        <div class="card-head"><h2 class="card-title">Yeni bildirim</h2></div>
        <form method="POST" action="{{ route('site.notifications.store', $apartment) }}" class="card-body space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2"><label class="label" for="title">Başlık *</label><input class="input" id="title" name="title" value="{{ old('title') }}" required></div>
                <div><label class="label" for="target">Hedef</label>
                    <select class="input" id="target" name="target" data-show-target>
                        <option value="all">Tüm site</option><option value="block">Bir blok</option><option value="debtors">Gecikmiş borcu olanlar</option><option value="units">Seçili daireler</option>
                    </select></div>
                <div data-target-for="block" hidden><label class="label" for="block_id">Blok</label><select class="input" id="block_id" name="block_id">@foreach ($blocks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
                <div data-target-for="units" hidden class="sm:col-span-3"><label class="label" for="unit_ids">Daireler (Ctrl ile çoklu seçim)</label>
                    <select class="input h-32" id="unit_ids" name="unit_ids[]" multiple>@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->label() }}</option>@endforeach</select></div>
            </div>
            <div><label class="label" for="body">Mesaj *</label><textarea class="input" id="body" name="body" rows="3" required>{{ old('body') }}</textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="send_email" value="1" class="rounded border-slate-300" @checked(old('send_email'))> Ayrıca üyelerin e-posta adreslerine gönder</label>
            <button class="btn btn-primary">Gönder</button>
            <p class="text-xs text-slate-500">Not: Bildirimler uygulama içinde saklanır. Telefona anlık (push) bildirim için ayrıca FCM gibi bir servis entegrasyonu gerekir.</p>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><h2 class="card-title">Gönderilenler</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tarih</th><th>Başlık</th><th>Hedef</th><th class="num">Okuyan</th></tr></thead>
                <tbody>
                @forelse ($notifications as $n)
                    <tr><td class="whitespace-nowrap">{{ $n->created_at->format('d.m.Y H:i') }}</td>
                        <td><span class="font-medium">{{ $n->title }}</span><span class="block text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($n->body, 120) }}</span></td>
                        <td>{{ $n->unit?->label() ?? 'Tüm site' }}</td><td class="num">{{ $n->readers_count }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-500">Bildirim yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $notifications->links() }}</div>
    </section>
</x-layout>
