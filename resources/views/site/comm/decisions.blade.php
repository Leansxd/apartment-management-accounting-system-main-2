<x-layout heading="Karar Defteri" :subheading="$apartment->name">
    <x-slot:actions><button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="printer" :size="15" />Yazdır / PDF</button></x-slot:actions>

    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <x-dash.filters :fields="[['name' => 'q', 'label' => 'Ara', 'type' => 'search', 'placeholder' => 'Konu veya karar no']]" />

    <details class="panel card no-print" @if($errors->any()) open @endif>
        <summary class="card-head"><span class="card-title">+ Karar ekle</span></summary>
        <form method="POST" action="{{ route('site.decisions.store', $apartment) }}" class="card-body space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-4">
                <div><label class="label" for="decision_no">Karar no *</label><input class="input" id="decision_no" name="decision_no" value="{{ old('decision_no', $nextNo) }}" required></div>
                <div><label class="label" for="decided_on">Tarih *</label><input class="input" id="decided_on" type="date" name="decided_on" value="{{ old('decided_on', today()->toDateString()) }}" required></div>
                <div class="sm:col-span-2"><label class="label" for="subject">Konu *</label><input class="input" id="subject" name="subject" value="{{ old('subject') }}" required></div>
            </div>
            <div><label class="label" for="content">Karar metni *</label><textarea class="input" id="content" name="content" rows="5" required>{{ old('content') }}</textarea></div>
            <div><label class="label" for="attendees">Katılımcılar</label><input class="input" id="attendees" name="attendees" value="{{ old('attendees') }}"></div>
            <button class="btn btn-primary">Kaydet</button>
        </form>
    </details>

    <x-report.print-header :apartment="$apartment" title="Karar Defteri" />
    <div class="space-y-3">
        @forelse ($decisions as $d)
            <article class="card card-body print-keep">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 class="font-semibold">Karar {{ $d->decision_no }} — {{ $d->subject }}</h2>
                    <div class="flex items-center gap-2"><span class="text-sm text-slate-500">{{ $d->decided_on->format('d.m.Y') }}</span>
                        <x-dash.edit-panel :action="route('site.decisions.update', [$apartment, $d])" width="w-[32rem]">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label class="label" for="de-no-{{ $d->id }}">Karar no *</label><input class="input" id="de-no-{{ $d->id }}" name="decision_no" maxlength="20" value="{{ $d->decision_no }}" required></div>
                                <div><label class="label" for="de-date-{{ $d->id }}">Tarih *</label><input class="input" id="de-date-{{ $d->id }}" type="date" name="decided_on" value="{{ $d->decided_on->toDateString() }}" required></div>
                            </div>
                            <div><label class="label" for="de-sub-{{ $d->id }}">Konu *</label><input class="input" id="de-sub-{{ $d->id }}" name="subject" maxlength="200" value="{{ $d->subject }}" required></div>
                            <div><label class="label" for="de-body-{{ $d->id }}">Karar metni *</label><textarea class="input" id="de-body-{{ $d->id }}" name="content" rows="5" maxlength="10000" required>{{ $d->content }}</textarea></div>
                            <div><label class="label" for="de-att-{{ $d->id }}">Katılanlar</label><input class="input" id="de-att-{{ $d->id }}" name="attendees" maxlength="500" value="{{ $d->attendees }}"></div>
                        </x-dash.edit-panel>
                        <form method="POST" action="{{ route('site.decisions.destroy', [$apartment, $d]) }}" class="no-print" onsubmit="return confirm('Karar silinsin mi?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Sil</button></form></div>
                </div>
                <p class="mt-2 text-sm whitespace-pre-line">{{ $d->content }}</p>
                @if ($d->attendees)<p class="mt-2 text-xs text-slate-500">Katılımcılar: {{ $d->attendees }}</p>@endif
            </article>
        @empty
            <p class="text-sm text-slate-500">Karar yok.</p>
        @endforelse
    </div>
    <x-report.signatures :roles="['Yönetici', 'Yönetim Kurulu Üyesi', 'Yönetim Kurulu Üyesi']" />
</x-layout>
