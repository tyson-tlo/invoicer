@props([
    'label',
    'tooltip' => null,
])

<div class="mb-3 flex items-center gap-1.5">
    <flux:label>{{ $label }}</flux:label>

    @if ($tooltip)
        <flux:tooltip :content="$tooltip" position="top">
            <button
                type="button"
                class="inline-flex size-4 shrink-0 items-center justify-center rounded-full text-zinc-400 transition hover:text-zinc-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 dark:text-zinc-500 dark:hover:text-zinc-300"
                aria-label="{{ __('More information') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
            </button>
        </flux:tooltip>
    @endif
</div>
