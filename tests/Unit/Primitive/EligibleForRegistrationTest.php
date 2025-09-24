<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Primitive;

it('returns true by default when shouldRegister is not defined', function (): void {
    $primitive = new class extends Primitive
    {
        public function toMethodCall(): array
        {
            return [];
        }

        public function toArray(): array
        {
            return [];
        }
    };

    $request = new Request([]);

    expect($primitive->eligibleForRegistration())->toBeTrue();
});

it('calls shouldRegister with the Request and honors its return value', function (): void {
    $primitive = new class extends Primitive
    {
        public ?Request $received = null;

        public function toMethodCall(): array
        {
            return [];
        }

        public function toArray(): array
        {
            return [];
        }

        public function shouldRegister(Request $request): bool
        {
            $this->received = $request;

            return false;
        }
    };

    $request = new Request(['foo' => 'bar']);

    $this->instance('mcp.request', $request);
    $result = $primitive->eligibleForRegistration();

    expect($result)->toBeFalse()
        ->and($primitive->received->all())->toBe($request->all());
});
