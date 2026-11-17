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
use Laravel\Mcp\Server\Skill;
use Laravel\Mcp\Server\Skills\SkillResource;
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

    public function tools(): TestListResponse
    {
        $server = $this->initializeServer();
        $tools = [];
        $cursor = null;

        do {
            $request = new JsonRpcRequest(
                uniqid(),
                'tools/list',
                $cursor !== null ? ['cursor' => $cursor] : [],
            );

            $response = $this->executeRequest($server, $request);

            if (! $response instanceof JsonRpcResponse) {
                throw new InvalidArgumentException('Expected a JsonRpcResponse for [tools/list].');
            }

            $result = $response->toArray()['result'] ?? null;

            if (! is_array($result) || ! is_array($result['tools'] ?? null)) {
                throw new InvalidArgumentException('Invalid tools/list response from server.');
            }

            $tools = [...$tools, ...$result['tools']];
            $cursor = is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : null;
        } while ($cursor !== null);

        return new TestListResponse($tools);
    }

    public function resources(): TestListResponse
    {
        $server = $this->initializeServer();
        $resources = [];
        $cursor = null;

        do {
            $request = new JsonRpcRequest(
                uniqid(),
                'resources/list',
                $cursor !== null ? ['cursor' => $cursor] : [],
            );

            $response = $this->executeRequest($server, $request);

            if (! $response instanceof JsonRpcResponse) {
                throw new InvalidArgumentException('Expected a JsonRpcResponse for [resources/list].');
            }

            $result = $response->toArray()['result'] ?? null;

            if (! is_array($result) || ! is_array($result['resources'] ?? null)) {
                throw new InvalidArgumentException('Invalid resources/list response from server.');
            }

            $resources = [...$resources, ...$result['resources']];
            $cursor = is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : null;
        } while ($cursor !== null);

        return new TestListResponse($resources);
    }

    public function prompts(): TestListResponse
    {
        $server = $this->initializeServer();
        $prompts = [];
        $cursor = null;

        do {
            $request = new JsonRpcRequest(
                uniqid(),
                'prompts/list',
                $cursor !== null ? ['cursor' => $cursor] : [],
            );

            $response = $this->executeRequest($server, $request);

            if (! $response instanceof JsonRpcResponse) {
                throw new InvalidArgumentException('Expected a JsonRpcResponse for [prompts/list].');
            }

            $result = $response->toArray()['result'] ?? null;

            if (! is_array($result) || ! is_array($result['prompts'] ?? null)) {
                throw new InvalidArgumentException('Invalid prompts/list response from server.');
            }

            $prompts = [...$prompts, ...$result['prompts']];
            $cursor = is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : null;
        } while ($cursor !== null);

        return new TestListResponse($prompts);
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
     * @param  Skill|class-string<Skill>|string  $skill
     */
    public function skill(Skill|string $skill): TestSkillResponse
    {
        $uri = $this->skillUri($skill);
        $server = $this->initializeServer();

        $response = $this->executeRequest($server, new JsonRpcRequest(uniqid(), 'skills/get', ['uri' => $uri]));

        if (! $response instanceof JsonRpcResponse) {
            throw new InvalidArgumentException('Expected a JsonRpcResponse for [skills/get].');
        }

        return new TestSkillResponse($uri, $response);
    }

    /**
     * @param  Skill|class-string<Skill>|string  $skill
     */
    public function skillFile(Skill|string $skill, string $file = 'SKILL.md'): TestResponse
    {
        $skill = is_string($skill) && str_contains($skill, '://')
            ? $this->initializeServer()->createContext()->skills()->first(fn (Skill $registered): bool => $registered->uri() === $skill)
            : $this->resolveSkill($skill);

        if (! $skill instanceof Skill) {
            throw new InvalidArgumentException('The skill is not registered on the server.');
        }

        return $this->run('resources/read', new SkillResource($skill, $file, basename($file), ''));
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

    protected function skillUri(Skill|string $skill): string
    {
        return is_string($skill) && str_contains($skill, '://') ? $skill : $this->resolveSkill($skill)->uri();
    }

    protected function resolveSkill(Skill|string $skill): Skill
    {
        return match (true) {
            $skill instanceof Skill => $skill,
            is_subclass_of($skill, Skill::class) => Container::getInstance()->make($skill),
            default => throw new InvalidArgumentException("The skill [{$skill}] must be a skill URI or a Skill class."),
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

        $id = uniqid();

        $request = new JsonRpcRequest($id, $method, [...$params, '_meta' => ['progressToken' => $id]]);

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
