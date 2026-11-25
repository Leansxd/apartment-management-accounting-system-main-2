<x-portal-layout title="Sakin Paneli">
    @if ($apartments->isNotEmpty())
        <section class="card">
            <div class="card-head"><h2 class="card-title">Sitelerim</h2></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($apartments as $apartment)
                    <li><a href="{{ route('portal.overview', $apartment) }}" class="block px-5 py-3 text-sm font-medium text-slate-900 hover:bg-slate-50">{{ $apartment->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @else
        <p class="text-sm text-slate-600">Hesabınız henüz bir daireye bağlı değil. Site yönetiminden aldığınız davet kodunu girin.</p>
    @endif

    <section class="card">
        <div class="card-head"><h2 class="card-title">Davet koduyla daire ekle</h2></div>
        <form method="POST" action="{{ route('portal.redeem') }}" class="card-body flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-56 flex-1"><label class="label" for="invite_code">Davet kodu</label><input class="input uppercase" id="invite_code" name="invite_code" required autocomplete="off"></div>
            <button class="btn btn-primary">Bağlan</button>
        </form>
    </section>
</x-portal-layout>
