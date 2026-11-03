<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <main class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 text-center md:p-10">
            <div class="flex flex-col items-center gap-5">
                @php
                    $branding = \App\Models\Setting::query()->first();
                    $brandingIcon = $branding?->iconUrl() ?? $branding?->logoUrl();
                @endphp

                @if ($brandingIcon)
                    <img
                        src="{{ $brandingIcon }}"
                        alt="{{ config('app.name', 'Invoicer') }}"
                        class="size-16 rounded-2xl object-contain"
                    >
                @else
                    <span class="flex size-16 items-center justify-center rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </span>
                @endif

                <div class="max-w-md">
                    <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name', 'Invoicer') }}</h1>
                    <p class="mt-3 text-base text-zinc-600 dark:text-zinc-400">
                        {{ __('A focused invoicing workspace for creating, sending, and tracking your invoices.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary">
                        {{ __('Go to dashboard') }}
                    </flux:button>

                    <flux:button :href="route('invoices.create')" variant="filled">
                        {{ __('New invoice') }}
                    </flux:button>
                @else
                    <flux:button :href="route('login')" variant="primary">
                        {{ __('Log in') }}
                    </flux:button>
                @endauth
            </div>
        </main>

        @fluxScripts
    </body>
</html>
