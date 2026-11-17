<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('denies framing of the authorize page through the http kernel', function (): void {
    Route::get('oauth/authorize', fn (): string => 'consent')->name('passport.authorizations.authorize');

    $this->get('oauth/authorize')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

it('does not add framing headers to other routes', function (): void {
    Route::get('dashboard', fn (): string => 'ok')->name('dashboard');

    $this->get('dashboard')
        ->assertOk()
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('Content-Security-Policy');
});
