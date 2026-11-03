<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Invoicer') : config('app.name', 'Invoicer') }}
</title>

@include('partials.favicon')

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
