<?php

use App\Http\Controllers\EmailProviderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/invoicing', 'pages::settings.invoicing')->name('settings.invoicing');
    Route::livewire('settings/branding', 'pages::settings.branding')->name('settings.branding');

    Route::livewire('settings/email', 'pages::settings.email')->name('settings.email');
    Route::get('settings/email/connect/{provider}', [EmailProviderController::class, 'redirect'])->name('settings.email.connect');
    Route::get('settings/email/callback/{provider}', [EmailProviderController::class, 'callback'])->name('settings.email.callback');

    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
