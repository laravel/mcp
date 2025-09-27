<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

class MakeServerCommand extends MakeMcpCommand
{
    /**
     * @var string
     */
    protected $type = 'Server';

    /**
     * @param  string  $name
     *
     * @throws FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);

        $serverDisplayName = trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', $className));

        return str_replace('{{ serverDisplayName }}', $serverDisplayName, $stub);
    }
}
