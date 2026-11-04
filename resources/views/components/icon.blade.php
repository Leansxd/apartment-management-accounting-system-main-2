@props(['name', 'size' => 16])
@php
    static $cache = [];
    $svg = $cache[$name] ??= is_file($file = resource_path("svg/{$name}.svg")) ? preg_replace('/<!--.*?-->\s*/s', '', file_get_contents($file)) : '';
    $svg = preg_replace(['/\swidth="24"/', '/\sheight="24"/', '/\sclass="[^"]*"/'], [' width="'.$size.'"', ' height="'.$size.'"', ' class="'.e($attributes->get('class')).' shrink-0" aria-hidden="true"'], $svg, 1);
@endphp
{!! $svg !!}
