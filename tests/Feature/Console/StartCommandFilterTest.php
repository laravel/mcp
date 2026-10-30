<?php

declare(strict_types=1);

use function Orchestra\Testbench\remote;

it('lists all tools when no filter options are given', function (): void {
    $process = remote(['mcp:start', 'filter-test']);
    $process->setInput(json_encode(listToolsMessage()));
    $process->run();

    $output = json_decode($process->getOutput(), true);
    $toolNames = array_column($output['result']['tools'], 'name');

    expect($toolNames)->toContain('tagged-tool')
        ->toContain('say-hi-tool');
})->skip(PHP_OS_FAMILY === 'Windows');

it('lists only the specified tool when --tool filter is given', function (): void {
    $process = remote(['mcp:start', 'filter-test', '--tool=Tests\\\\Fixtures\\\\TaggedTool']);
    $process->setInput(json_encode(listToolsMessage()));
    $process->run();

    $output = json_decode($process->getOutput(), true);
    $toolNames = array_column($output['result']['tools'], 'name');

    expect($toolNames)->toContain('tagged-tool')
        ->not->toContain('say-hi-tool');
})->skip(PHP_OS_FAMILY === 'Windows');

it('lists only tagged tools when --tag filter is given', function (): void {
    $process = remote(['mcp:start', 'filter-test', '--tag=weather']);
    $process->setInput(json_encode(listToolsMessage()));
    $process->run();

    $output = json_decode($process->getOutput(), true);
    $toolNames = array_column($output['result']['tools'], 'name');

    expect($toolNames)->toContain('tagged-tool')
        ->not->toContain('say-hi-tool');
})->skip(PHP_OS_FAMILY === 'Windows');

it('uses union semantics when both --tool and --tag filters are given', function (): void {
    $process = remote(['mcp:start', 'filter-test', '--tool=Tests\\\\Fixtures\\\\SayHiTool', '--tag=weather']);
    $process->setInput(json_encode(listToolsMessage()));
    $process->run();

    $output = json_decode($process->getOutput(), true);
    $toolNames = array_column($output['result']['tools'], 'name');

    expect($toolNames)->toContain('tagged-tool')
        ->toContain('say-hi-tool');
})->skip(PHP_OS_FAMILY === 'Windows');
