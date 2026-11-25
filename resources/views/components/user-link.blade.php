@props(['user' => null, 'fallback' => '—'])
{{-- Kullanıcı adı: kullanıcı sayfası her yöneticiye açık olmadığından bağlantı yalnızca süper admine verilir. --}}
@if (! $user){{ $fallback }}@elseif (auth()->user()?->is_super_admin)<a href="{{ route('users.show', $user) }}" {{ $attributes->merge(['class' => 'hover:text-brand-600 hover:underline']) }}>{{ $user->name }}</a>@else{{ $user->name }}@endif