<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;

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
        return $this->run('prompts/get', $prompt, $arguments);
    }

    /**
     * @param  class-string<Resource>|Resource  $resource
     * @param  array<string, mixed>  $arguments
     */
    public function resource(Resource|string $resource, array $arguments = []): TestResponse
    {
        return $this->run('resources/read', $resource, $arguments);
    }

    public function actingAs(Authenticatable $user, ?string $guard = null): static
    {
        if (property_exists($user, 'wasRecentlyCreated')) {
            $user->wasRecentlyCreated = false;
        }

        $this->app['auth']->guard($guard)->setUser($user);

        $this->app['auth']->shouldUse($guard);

        return $this;
    }

    /**
     * @param  class-string<Primitive>|Primitive  $primitive
     * @param  array<string, mixed>  $arguments
     *
     * @throws JsonRpcException
     */
    protected function run(string $method, Primitive|string $primitive, array $arguments = []): TestResponse
    {
        $container = Container::getInstance();

        $primitive = is_string($primitive) ? $container->make($primitive) : $primitive;
        $server = $container->make($this->serverClass, ['transport' => new FakeTransporter]);

        $server->start();

        $requestId = uniqid();

        $request = new JsonRpcRequest(
            $requestId,
            $method,
            [
                ...$primitive->toMethodCall(),
                'arguments' => $arguments,
            ],
        );

        try {
            $response = (fn () => $this->runMethodHandle($request, $this->createContext()))->call($server);
        } catch (JsonRpcException $jsonRpcException) {
            $response = $jsonRpcException->toJsonRpcResponse();
        }

        return new TestResponse($primitive, $response);
    }
}
