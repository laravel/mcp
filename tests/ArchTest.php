<?php

arch('Strict and safe')
    ->expect('Laravel\Mcp')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump', 'var_dump']);

arch('MCP methods extend base class')
    ->expect('Laravel\Mcp\Server\Methods')
    ->toOnlyImplement('Laravel\Mcp\Server\Contracts\Methods\Method');

arch('Tool annotations implement annotation interface')
    ->expect('Laravel\Mcp\Server\Tools\Annotations')
    ->toOnlyImplement('Laravel\Mcp\Server\Contracts\Tools\Annotation');

arch('Contracts are interfaces')
    ->expect('Laravel\Mcp\Server\Contracts\*')
    ->toBeInterfaces();

arch('Exceptions extend')
    ->expect('Laravel\Mcp\Server\Exceptions')
    ->toExtend(Exception::class);

arch('Commands extend command')
    ->expect('Laravel\Mcp\Console\Commands')
    ->toExtend(\Illuminate\Console\Command::class)
    ->toHaveAttribute(\Symfony\Component\Console\Attribute\AsCommand::class);
