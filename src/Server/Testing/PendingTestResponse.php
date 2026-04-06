<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class PendingTestResponse
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $elicitationExpectations = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $clientCapabilities = null;

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

    /**
     * @param  class-string<Primitive>|Primitive  $primitive
     * @param  array<string, mixed>  $currentArgs
     */
    public function completion(
        Primitive|string $primitive,
        string $argumentName,
        string $argumentValue = '',
        array $currentArgs = []
    ): TestResponse {
        $primitive = $this->resolvePrimitive($primitive);
        $server = $this->initializeServer();

        $request = new JsonRpcRequest(
            uniqid(),
            'completion/complete',
            [
                'ref' => $this->buildCompletionRef($primitive),
                'argument' => [
                    'name' => $argumentName,
                    'value' => $argumentValue,
                ],
                'context' => [
                    'arguments' => $currentArgs,
                ],
            ],
        );

        $response = $this->executeRequest($server, $request);

        return new TestResponse($primitive, $response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCompletionRef(Primitive $primitive): array
    {
        return match (true) {
            $primitive instanceof Prompt => [
                'type' => 'ref/prompt',
                'name' => $primitive->name(),
            ],
            $primitive instanceof Resource => [
                'type' => 'ref/resource',
                'uri' => $primitive->uri(),
            ],
            default => throw new InvalidArgumentException('Unsupported primitive type for completion.'),
        };
    }

    protected function resolvePrimitive(Primitive|string $primitive): Primitive
    {
        return is_string($primitive)
            ? Container::getInstance()->make($primitive)
            : $primitive;
    }

    /**
     * @param  array<string, mixed>  $respondWith
     * @param  array<string, mixed>  $capabilities
     */
    public function elicitation(array $respondWith, array $capabilities = [Server::CAPABILITY_ELICITATION => ['form' => [], 'url' => []]]): static
    {
        $this->clientCapabilities = $capabilities;
        $this->elicitationExpectations[] = $respondWith;

        return $this;
    }

    protected function initializeServer(): Server
    {
        $transport = new FakeTransporter;

        if ($this->clientCapabilities !== null) {
            $transport->setClientCapabilities($this->clientCapabilities);
        }

        foreach ($this->elicitationExpectations as $expectation) {
            $transport->expectElicitation($expectation);
        }

        $server = Container::getInstance()->make(
            $this->serverClass,
            ['transport' => $transport]
        );

        $server->start();

        return $server;
    }

    protected function executeRequest(Server $server, JsonRpcRequest $request): mixed
    {
        try {
            return (fn (): iterable|JsonRpcResponse => $this->runMethodHandle($request, $this->createContext()))->call($server);
        } catch (JsonRpcException $jsonRpcException) {
            $response = $jsonRpcException->toJsonRpcResponse();
            $content = $response->toArray();

            if (! isset($content['id'])) {
                return JsonRpcResponse::error(
                    id: $request->id,
                    code: $content['error']['code'],
                    message: $content['error']['message'],
                    data: $content['error']['data'] ?? null,
                );
            }

            return $response;
        }
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
        $primitive = $this->resolvePrimitive($primitive);
        $server = $this->initializeServer();

        $request = new JsonRpcRequest(
            uniqid(),
            $method,
            [
                ...$primitive->toMethodCall(),
                'arguments' => $arguments,
            ],
        );

        $response = $this->executeRequest($server, $request);

        $transport = (fn (): Transport => $this->transport)->call($server);

        return new TestResponse($primitive, $response, $transport instanceof FakeTransporter ? $transport : null);
    }
}
