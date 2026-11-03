@props(['debt'])
@php
    $state = $debt->displayStatus();
    $class = ['paid' => 'badge-green', 'overdue' => 'badge-red', 'partial' => 'badge-amber', 'pending' => 'badge-gray'][$state];
@endphp
<span class="badge {{ $class }}">{{ \App\Models\Debt::DISPLAY[$state] }}</span>
