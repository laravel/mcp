<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Primitive;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Support\UriTemplate;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Stringable;

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

    protected function initializeServer(): Server
    {
        $server = Container::getInstance()->make(
            $this->serverClass,
            ['transport' => new FakeTransporter]
        );

        $server->start();

        return $server;
    }

    protected function executeRequest(Server $server, JsonRpcRequest $request): mixed
    {
        try {
            return (fn (): iterable|JsonRpcResponse => $this->runMethodHandle($request, $this->createContext()))->call($server);
        } catch (JsonRpcException $jsonRpcException) {
            return $jsonRpcException->toJsonRpcResponse();
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

        $params = [
            ...$primitive->toMethodCall(),
            'arguments' => $arguments,
        ];

        if ($method === 'resources/read' && $primitive instanceof HasUriTemplate) {
            $params['uri'] = $this->expandUriTemplate($primitive->uriTemplate(), $arguments);
        }

        $request = new JsonRpcRequest(uniqid(), $method, $params);

        $response = $this->executeRequest($server, $request);

        return new TestResponse($primitive, $response);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected function expandUriTemplate(UriTemplate $template, array $variables): string
    {
        $expanded = (string) $template;

        foreach ($template->variableNames() as $name) {
            if (! array_key_exists($name, $variables)) {
                throw new InvalidArgumentException("Missing value for URI template variable [{$name}].");
            }

            $value = $variables[$name];

            if (! is_scalar($value) && ! $value instanceof Stringable) {
                throw new InvalidArgumentException("URI template variable [{$name}] must be a scalar or Stringable value.");
            }

            $value = (string) $value;

            if (str_contains($value, '/')) {
                throw new InvalidArgumentException("URI template variable [{$name}] value must not contain '/'.");
            }

            $expanded = str_replace('{'.$name.'}', $value, $expanded);
        }

        return $expanded;
    }
}
