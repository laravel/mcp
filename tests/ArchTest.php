<?php

arch('strict and safe')
    ->expect('Laravel\Mcp')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump', 'var_dump']);

arch('mcp methods extend base class')
    ->expect('Laravel\Mcp\Server\Methods')
    ->toOnlyImplement('Laravel\Mcp\Server\Contracts\Method');

arch('tool annotations implement annotation interface')
    ->expect('Laravel\Mcp\Server\Tools\Annotations')
    ->toOnlyImplement('Laravel\Mcp\Server\Contracts\Tools\Annotation');

arch('contracts are interfaces')
    ->expect('Laravel\Mcp\Server\Contracts\*')
    ->toBeInterfaces();

arch('exceptions extend')
    ->expect('Laravel\Mcp\Server\Exceptions')
    ->toExtend(Exception::class);

arch('commands extend command')
    ->expect('Laravel\Mcp\Console\Commands')
    ->toExtend(\Illuminate\Console\Command::class)
    ->toHaveAttribute(\Symfony\Component\Console\Attribute\AsCommand::class);
