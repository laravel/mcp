<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('may attempt to authenticate', function (): void {
    Route::get('/login')->name('login');

    Route::get('/attempt-authenticate', fn (Request $request): string => $request?->user() ? 'authenticated' : 'guest')->middleware('mcp.auth');

    $response = $this->get('/attempt-authenticate');

    $response->assertOk()
        ->assertSee('guest');
});

it('may authenticate', function (): void {
    $user = new class extends User {};

    Route::get('/login')->name('login');

    Route::get('/attempt-authenticate', fn (Request $request): string => $request?->user() ? 'authenticated' : 'guest')->middleware('mcp.auth');

    $response = $this->actingAs($user)->get('/attempt-authenticate');

    $response->assertOk()
        ->assertSee('authenticated');
});
