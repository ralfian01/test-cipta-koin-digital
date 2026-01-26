<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\REST\V1 as RESTV1;
use App\Http\Controllers\REST\Errors;

## Authorization
Route::prefix('auth')->group(function () {
    Route::post('account', [RESTV1\Auth\Account::class, 'index']);
});

## Account
Route::prefix('account')->group(function () {
    Route::post('register', [RESTV1\Account\Register\Insert::class, 'index']);
});

## My Data
Route::prefix('my')->middleware(['auth:bearer'])->group(function () {
    // Route::prefix('my')->middleware('bearer')->group(function () {
    // Get my data
    // Route::get('/', [RESTV1\My\Data::class, 'index']);

    // Get my privileges
    Route::get('privileges', [RESTV1\My\Privileges\Get::class, 'index']);

    // Manage profile data
    Route::prefix('profile')->group(function () {
        Route::get('/', [RESTV1\My\Profile\Get::class, 'index']);
        Route::put('/', [RESTV1\My\Profile\Update::class, 'index']);
    });

    // Manage todo list
    Route::prefix('todo')->group(function () {
        Route::get('/', [RESTV1\My\Todo\Get::class, 'index']);
        Route::post('/', [RESTV1\My\Todo\Insert::class, 'index']);
        Route::patch('{id}', [RESTV1\My\Todo\Update::class, 'index']);
        Route::delete('{id}', [RESTV1\My\Todo\Delete::class, 'index']);
    });
});

## Manage
Route::prefix('manage')->middleware(['auth:bearer', 'bo'])->group(function () {

    Route::prefix('roles')->group(function () {
        Route::get('/', [RESTV1\Manage\Roles\Get::class, 'index']);
    });

    Route::prefix('accounts')->group(function () {
        Route::get('/', [RESTV1\Manage\Accounts\Get::class, 'index']);
        Route::get('{id}', [RESTV1\Manage\Accounts\Get::class, 'index']);
        Route::post('/', [RESTV1\Manage\Accounts\Insert::class, 'index']);
        Route::put('{id}', [RESTV1\Manage\Accounts\Update::class, 'index']);
        Route::delete('{id}', [RESTV1\Manage\Accounts\Delete::class, 'index']);
    });
});
