<x-layout heading="Rapor Merkezi" subheading="Her rapor yazdırılabilir (PDF olarak kaydedilebilir) ve Excel'e aktarılabilir">
    @foreach ($types as $group => $items)
        <section>
            <h2 class="mb-3 text-sm font-semibold text-slate-500 uppercase tracking-wide">{{ $group }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $key => $type)
                    <div class="card card-body flex flex-col gap-3">
                        <h3 class="font-semibold text-slate-900">{{ $type[0] }}</h3>
                        <div class="mt-auto flex gap-2">
                            <a class="btn btn-primary btn-sm" href="{{ route('site.reports.show', [$apartment, $key]) }}">Görüntüle</a>
                            <a class="btn btn-secondary btn-sm" href="{{ route('site.reports.show', [$apartment, $key, 'format' => 'csv']) }}">Excel (CSV)</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</x-layout>
