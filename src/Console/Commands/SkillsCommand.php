<?php

declare(strict_types=1);

namespace Laravel\Mcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Throwable;

#[AsCommand(
    name: 'mcp:skills',
    description: 'List and validate the skills served by an MCP server'
)]
class SkillsCommand extends Command
{
    public function handle(): int
    {
        $argument = $this->argument('server');
        $name = is_string($argument) ? $argument : '';
        $class = class_exists($name) ? $name : $this->laravel->getNamespace().'Mcp\\Servers\\'.$name;

        if (! is_subclass_of($class, Server::class)) {
            $this->components->error("MCP server [{$name}] not found.");

            return static::FAILURE;
        }

        $server = Container::getInstance()->make($class, ['transport' => new FakeTransporter]);
        $server->start();

        try {
            $context = $server->createContext();
            $catalog = $context->skillCatalog();
            $context->resources();
        } catch (Throwable $throwable) {
            $this->components->error($throwable->getMessage());

            return static::FAILURE;
        }

        $rows = $catalog->skills()->map(function (Skill $skill): array {
            $entry = $skill->toArray();

            return [
                $entry['uri'],
                count($entry['resources']),
                number_format(array_sum(array_column($entry['resources'], 'size')) / 1024, 1).' KiB',
            ];
        })->all();

        if ($rows !== []) {
            $this->table(['URI', 'Files', 'Size'], $rows);
        }

        foreach ($catalog->errors() as $error) {
            $this->components->error($error->getMessage());
        }

        if ($catalog->errors() !== []) {
            return static::FAILURE;
        }

        $this->components->info(count($rows).' '.str('skill')->plural(count($rows)).' valid.');

        return static::SUCCESS;
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getArguments(): array
    {
        return [
            ['server', InputArgument::REQUIRED, 'The MCP server class'],
        ];
    }
}
