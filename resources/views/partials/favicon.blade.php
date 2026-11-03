@php
    $branding = \App\Models\Setting::query()->first();
    $brandingIcon = $branding?->iconUrl() ?? $branding?->logoUrl();
@endphp

@if ($brandingIcon)
    <link rel="icon" href="{{ $brandingIcon }}">
    <link rel="apple-touch-icon" href="{{ $brandingIcon }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
