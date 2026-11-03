<?php

use App\Http\Controllers\InvoicePdfController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('customers', 'pages::customers')->name('customers');

    Route::livewire('invoices', 'pages::invoices')->name('invoices');
    Route::livewire('invoices/create', 'pages::invoices.create')->name('invoices.create');
    Route::livewire('invoices/{invoice}/edit', 'pages::invoices.create')->name('invoices.edit');
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'view'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/pdf/download', [InvoicePdfController::class, 'download'])->name('invoices.pdf.download');
    Route::livewire('invoices/{invoice}', 'pages::invoices.show')->name('invoices.show');
});

require __DIR__.'/settings.php';
