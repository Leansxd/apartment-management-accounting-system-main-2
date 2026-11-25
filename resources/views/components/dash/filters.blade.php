{{-- $fields: [['name' => 'status', 'label' => 'Durum', 'type' => 'select|date|month|search|check', 'options' => [değer => etiket]]] · $keep: korunacak sorgu parametreleri --}}
@props(['fields' => [], 'keep' => []])
<form method="GET" class="no-print card card-body !py-4 flex flex-wrap items-end gap-3" data-filter-bar>
    @foreach ($keep as $name)
        @if (filled(request()->query($name)))<input type="hidden" name="{{ $name }}" value="{{ request()->query($name) }}">@endif
    @endforeach
    @foreach ($fields as $field)
        @php $id = 'filter-'.$field['name']; $current = (string) request()->query($field['name']); @endphp
        @if ($field['type'] === 'check')
            <label class="flex items-center gap-2 pb-2 text-xs text-slate-500">
                <input type="checkbox" name="{{ $field['name'] }}" value="1" class="rounded border-slate-300" @checked($current === '1')>{{ $field['label'] }}
            </label>
        @else
            <div class="min-w-[9rem]">
                <label class="label" for="{{ $id }}">{{ $field['label'] }}</label>
                @if ($field['type'] === 'select')
                    <select class="input" id="{{ $id }}" name="{{ $field['name'] }}">
                        <option value="">Tümü</option>
                        @foreach ($field['options'] as $value => $text)<option value="{{ $value }}" @selected($current === (string) $value)>{{ $text }}</option>@endforeach
                    </select>
                @else
                    <input class="input" id="{{ $id }}" type="{{ $field['type'] }}" name="{{ $field['name'] }}" value="{{ $current }}" @if ($field['type'] === 'search') placeholder="{{ $field['placeholder'] ?? 'Ara' }}" @endif>
                @endif
            </div>
        @endif
    @endforeach
    <button class="btn btn-secondary">Filtrele</button>
    @if (collect($fields)->contains(fn ($field) => filled(request()->query($field['name']))))
        <a class="btn btn-sm text-slate-500" href="{{ request()->url().($keep ? '?'.http_build_query(request()->only($keep)) : '') }}">Temizle</a>
    @endif
</form>
