<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class PendingTestResponse
{
    /**
     * @param  class-string<Server>  $serverClass
     */
    public function __construct(
        protected Container $app,
        protected string $serverClass
    ) {
        //
    }

    /**
     * @param  class-string<Primitive>|Primitive  $primitive
     * @param  array<string, mixed>  $arguments
     */
    public function run(string $method, Primitive|string $primitive, array $arguments = []): TestResponse
    {
        $container = Container::getInstance();

        $primitive = is_string($primitive) ? $container->make($primitive) : $primitive;
        $server = $container->make($this->serverClass, ['transport' => new FakeTransporter]);

        /** @var Method $methodInstance = */
        $methodInstance = $container->make(match ($method) {
            'tools/call' => CallTool::class,
            'prompts/get' => GetPrompt::class,
            default => throw new InvalidArgumentException("Unsupported [{$method}] method."),
        });

        $response = $methodInstance->handle(new JsonRpcRequest(
            uniqid(),
            $method,
            ['name' => $primitive->name(), 'arguments' => $arguments],
        ), $server->createContext());

        return new TestResponse($primitive, $response);
    }

    /**
     * @param  class-string<Tool>|Tool  $tool
     * @param  array<string, mixed>  $arguments
     */
    public function tool(Tool|string $tool, array $arguments = []): TestResponse
    {
        return $this->run('tools/call', $tool, $arguments);
    }

    /**
     * @param  class-string<Prompt>|Prompt  $prompt
     * @param  array<string, mixed>  $arguments
     */
    public function prompt(Prompt|string $prompt, array $arguments = []): TestResponse
    {
        try {
            return $this->run('prompts/get', $prompt, $arguments);
        } catch (JsonRpcException $jsonRpcException) {
            $prompt = is_string($prompt) ? Container::getInstance()->make($prompt) : $prompt;

            return new TestResponse($prompt, JsonRpcResponse::error(
                uniqid(),
                $jsonRpcException->getCode(),
                $jsonRpcException->getMessage(),
            ));
        }
    }

    public function actingAs(Authenticatable $user, ?string $guard = null): static
    {
        if (property_exists($user, 'wasRecentlyCreated') && $user->wasRecentlyCreated !== null && $user->wasRecentlyCreated) {
            $user->wasRecentlyCreated = false;
        }

        $this->app['auth']->guard($guard)->setUser($user);

        $this->app['auth']->shouldUse($guard);

        return $this;
    }
}
