<?php

use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Branding settings')] class extends Component {
    use WithFileUploads;

    public $logo = null;
    public $icon = null;
    public bool $removeLogo = false;
    public bool $removeIcon = false;

    public function save(): void
    {
        $this->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp,ico', 'max:1024'],
        ]);

        $settings = Setting::current();

        $logoPath = $settings->logo_path;

        if ($this->logo) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $storedPath = $this->logo->store('logos', 'public');

            if ($storedPath !== false) {
                $logoPath = $storedPath;
            }
        }

        if ($this->removeLogo && ! $this->logo && $logoPath) {
            Storage::disk('public')->delete($logoPath);

            $logoPath = null;
        }

        $iconPath = $settings->icon_path;

        if ($this->icon) {
            if ($settings->icon_path) {
                Storage::disk('public')->delete($settings->icon_path);
            }

            $storedPath = $this->icon->store('icons', 'public');

            if ($storedPath !== false) {
                $iconPath = $storedPath;
            }
        }

        if ($this->removeIcon && ! $this->icon && $iconPath) {
            Storage::disk('public')->delete($iconPath);

            $iconPath = null;
        }

        $settings->update([
            'logo_path' => $logoPath,
            'icon_path' => $iconPath,
        ]);

        $this->logo = null;
        $this->icon = null;
        $this->removeLogo = false;
        $this->removeIcon = false;

        Flux::toast(variant: 'success', text: __('Branding settings saved.'));
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return Setting::current()->logoUrl();
    }

    #[Computed]
    public function iconUrl(): ?string
    {
        return Setting::current()->iconUrl();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Branding settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Branding')" :subheading="__('Upload the logo used on invoices and the icon used in the browser tab')">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Logo') }}</flux:heading>
                    <flux:subheading>{{ __('Your logo appears on generated invoice PDFs.') }}</flux:subheading>
                </div>

                @if ($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="{{ __('Logo preview') }}" class="max-h-24 w-auto rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
                @elseif ($this->logoUrl)
                    <img src="{{ $this->logoUrl }}" alt="{{ __('Current logo') }}" class="max-h-24 w-auto rounded-lg border border-neutral-200 p-2 dark:border-neutral-700">
                @endif

                <flux:input wire:model="logo" label="{{ __('Upload logo') }}" type="file" accept="image/*" />

                @if ($this->logoUrl)
                    <flux:checkbox wire:model="removeLogo" label="{{ __('Remove current logo') }}" />
                @endif
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div>
                    <flux:heading>{{ __('Browser icon') }}</flux:heading>
                    <flux:subheading>{{ __('Shown in the browser tab. PNG, SVG, or ICO recommended.') }}</flux:subheading>
                </div>

                @if ($icon)
                    <img src="{{ $icon->temporaryUrl() }}" alt="{{ __('Icon preview') }}" class="size-12 rounded-lg border border-neutral-200 p-1 dark:border-neutral-700">
                @elseif ($this->iconUrl)
                    <img src="{{ $this->iconUrl }}" alt="{{ __('Current icon') }}" class="size-12 rounded-lg border border-neutral-200 p-1 dark:border-neutral-700">
                @endif

                <flux:input wire:model="icon" label="{{ __('Upload icon') }}" type="file" accept="image/png,image/svg+xml,image/x-icon,image/*" />

                @if ($this->iconUrl)
                    <flux:checkbox wire:model="removeIcon" label="{{ __('Remove current icon') }}" />
                @endif
            </flux:card>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">
                    {{ __('Save branding') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
