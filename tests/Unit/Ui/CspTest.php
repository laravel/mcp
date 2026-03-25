<?php

use Laravel\Mcp\Server\Ui\Csp;

it('serializes as empty when no domains set', function (): void {
    expect(Csp::make()->toArray())->toEqual([]);
});

it('serializes connect domains', function (): void {
    $csp = Csp::make()->connectDomains(['https://api.example.com']);

    expect($csp->toArray())->toEqual([
        'connectDomains' => ['https://api.example.com'],
    ]);
});

it('serializes all domain types', function (): void {
    $csp = Csp::make()
        ->connectDomains(['https://api.example.com'])
        ->resourceDomains(['https://cdn.example.com'])
        ->frameDomains(['https://www.youtube.com'])
        ->baseUriDomains(['https://base.example.com']);

    expect($csp->toArray())->toEqual([
        'connectDomains' => ['https://api.example.com'],
        'resourceDomains' => ['https://cdn.example.com'],
        'frameDomains' => ['https://www.youtube.com'],
        'baseUriDomains' => ['https://base.example.com'],
    ]);
});

it('supports constructor parameters', function (): void {
    $csp = new Csp(
        connectDomains: ['https://api.example.com'],
        resourceDomains: ['https://cdn.example.com'],
    );

    expect($csp->toArray())->toEqual([
        'connectDomains' => ['https://api.example.com'],
        'resourceDomains' => ['https://cdn.example.com'],
    ]);
});

it('implements Arrayable', function (): void {
    expect(new Csp)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
});
