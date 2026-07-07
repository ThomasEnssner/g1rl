<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::lock-picker')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
