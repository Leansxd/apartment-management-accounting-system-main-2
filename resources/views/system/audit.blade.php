<x-layout heading="Sistem Kayıtları" subheading="Tüm sitelerin ve oturum işlemlerinin denetim izi">
    <x-dash.filters :fields="[['name' => 'module', 'label' => 'Modül', 'type' => 'select', 'options' => $modules], ['name' => 'from', 'label' => 'Başlangıç', 'type' => 'date'], ['name' => 'to', 'label' => 'Bitiş', 'type' => 'date'], ['name' => 'q', 'label' => 'Ara', 'type' => 'search', 'placeholder' => 'İşlem, referans veya IP']]" />

    @include('site._audit-table', ['logs' => $logs, 'showSite' => true])
</x-layout>
