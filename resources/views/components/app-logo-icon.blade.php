@props([
    'size' => null,
])

<img
    src="{{ asset('images/logo.jpg') }}"
    alt="{{ config('app.name', 'SIFD') }} Logo"
    {{ $attributes->merge(['class' => 'object-contain rounded-lg select-none shadow-xs']) }}
/>
