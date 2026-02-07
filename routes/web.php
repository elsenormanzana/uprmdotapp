<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    $role = auth()->user()->role ?? 'student';

    return match ($role) {
        'admin' => redirect()->route('admin.dashboard'),
        'security_guard' => redirect()->route('guard.dashboard'),
        default => redirect()->route('student.dashboard'),
    };
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'pages.admin.dashboard')->name('dashboard');
    Route::livewire('infractions', 'pages::admin.infractions')->name('infractions');
    Route::livewire('permit-types', 'pages::admin.permit-types')->name('permit-types');
    Route::livewire('permit-zones', 'pages::admin.permit-zones')->name('permit-zones');
    Route::livewire('assign-permit', 'pages::admin.assign-permit')->name('assign-permit');
});

Route::middleware(['auth', 'verified', 'role:security_guard'])->prefix('guard')->name('guard.')->group(function () {
    Route::view('/', 'pages.guard.dashboard')->name('dashboard');
    Route::livewire('issue-infraction', 'pages::guard.issue-infraction')->name('issue-infraction');
    Route::livewire('validate-student', 'pages::guard.validate-student')->name('validate-student');
});

Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::view('/', 'pages.student.dashboard')->name('dashboard');
    Route::livewire('vehicles', 'pages::student.vehicles')->name('vehicles');
    Route::livewire('infractions', 'pages::student.infractions')->name('infractions');
    Route::livewire('campus-map', 'pages::student.campus-map')->name('campus-map');
    Route::livewire('profile', 'pages::student.profile')->name('profile');
});
