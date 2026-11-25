<x-layout heading="İşlem Kayıtları" subheading="Finansal ve yönetimsel işlemlerin denetim izi (silinemez)">
    <x-dash.module-summary :summary="$moduleSummary ?? null" />

    <x-dash.filters :fields="[['name' => 'module', 'label' => 'Modül', 'type' => 'select', 'options' => $modules], ['name' => 'user_id', 'label' => 'Kullanıcı', 'type' => 'select', 'options' => $users], ['name' => 'from', 'label' => 'Başlangıç', 'type' => 'date'], ['name' => 'to', 'label' => 'Bitiş', 'type' => 'date'], ['name' => 'q', 'label' => 'Ara', 'type' => 'search', 'placeholder' => 'İşlem veya referans']]" />

    @include('site._audit-table', ['logs' => $logs])
</x-layout>
