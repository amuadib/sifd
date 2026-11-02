<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('files', 'pages::files.index')->name('files.index');
    Route::livewire('files/{file}', 'pages::files.show')->name('files.show');
    Route::livewire('scanner', 'pages::scanner.index')->name('scanner.index');
    Route::livewire('storage-sources', 'pages::storage-sources.index')->name('storage-sources.index');
    Route::livewire('categories', 'pages::categories.index')->name('categories.index');
    Route::livewire('tags', 'pages::tags.index')->name('tags.index');
    Route::livewire('scan-histories', 'pages::scan-histories.index')->name('scan-histories.index');
});

require __DIR__.'/settings.php';
