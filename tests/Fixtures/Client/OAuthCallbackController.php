<?php

declare(strict_types=1);

namespace Tests\Fixtures\Client;

use Illuminate\Http\RedirectResponse;
use Laravel\Mcp\Client\OAuth\TokenSet;

class OAuthCallbackController
{
    public function callback(string $provider, TokenSet $token): RedirectResponse
    {
        return redirect("/connected/{$provider}");
    }
}
