<?php

use Illuminate\Console\Command;
use Laravel\Mcp\Server\Contracts\Annotation;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Testing\TestResponse;
use Symfony\Component\Console\Attribute\AsCommand;

arch('strict and safe')
    ->expect('Laravel\Mcp')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump', 'var_dump'])
    ->ignoring(TestResponse::class);

arch('mcp methods extend base class')
    ->expect('Laravel\Mcp\Server\Methods')
    ->toImplement([Method::class])
    ->ignoring('Laravel\Mcp\Server\Methods\Concerns');

arch('tool annotations implement annotation interface')
    ->expect('Laravel\Mcp\Server\Tools\Annotations')
    ->toOnlyImplement(Annotation::class);

arch('resource annotations implement annotation interface')
    ->expect('Laravel\Mcp\Server\Resources\Annotations')
    ->toOnlyImplement(Annotation::class);

arch('contracts are interfaces')
    ->expect('Laravel\Mcp\Server\Contracts\*')
    ->toBeInterfaces();

arch('exceptions extend')
    ->expect('Laravel\Mcp\Server\Exceptions')
    ->toExtend(Exception::class);

arch('commands extend command')
    ->expect('Laravel\Mcp\Console\Commands')
    ->toExtend(Command::class)
    ->toHaveAttribute(attribute: AsCommand::class);
