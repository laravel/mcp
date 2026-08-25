<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Laravel\Mcp\Enums\ErrorCode;
use Laravel\Mcp\Enums\Extension;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Enums\ProtocolVersion;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Schema\Implementation;
use Laravel\Mcp\Server\AppResource;
use Laravel\Mcp\Server\Attributes\Cacheable;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Concerns\HasIcons;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Methods\CompletionComplete;
use Laravel\Mcp\Server\Methods\Concerns\ResolvesResources;
use Laravel\Mcp\Server\Methods\Discover;
use Laravel\Mcp\Server\Methods\GetPrompt;
use Laravel\Mcp\Server\Methods\Listen;
use Laravel\Mcp\Server\Methods\ListPrompts;
use Laravel\Mcp\Server\Methods\ListResources;
use Laravel\Mcp\Server\Methods\ListResourceTemplates;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\Methods\ReadResource;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Mcp\Server\Testing\TestListResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Transport\JsonRpcNotification;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use stdClass;
use Throwable;

/**
 * @mixin PendingTestResponse
 */
abstract class Server
{
    use HasIcons;
    use ResolvesResources;

    public const CAPABILITY_TOOLS = 'tools';

    public const CAPABILITY_RESOURCES = 'resources';

    public const CAPABILITY_PROMPTS = 'prompts';

    public const CAPABILITY_COMPLETIONS = 'completions';

    public const CACHEABLE_METHODS = [
        'server/discover',
        'tools/list',
        'prompts/list',
        'resources/list',
        'resources/templates/list',
        'resources/read',
    ];

    protected string $name = 'Laravel MCP Server';

    protected string $version = '0.0.1';

    protected string $instructions = <<<'MARKDOWN'
        This MCP server lets AI agents interact with our Laravel application.
    MARKDOWN;

    /**
     * @var array<int, string>
     */
    protected array $supportedProtocolVersion = [];

    /**
     * @var array<int, Extension>
     */
    protected array $extensions = [];

    /**
     * @var array<string, array<string, bool>|stdClass|string>
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
        self::CAPABILITY_PROMPTS => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int|string, Tool|class-string<Tool>|array<int, Tool|class-string<Tool>>>
     */
    protected array $tools = [];

    /**
     * @var array<int, Resource|class-string<Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, Prompt|class-string<Prompt>>
     */
    protected array $prompts = [];

    public int $maxPaginationLength = 50;

    public int $defaultPaginationLength = 15;

    /**
     * @var array<string, class-string<Method>>
     */
    protected array $methods = [
        'tools/list' => ListTools::class,
        'tools/call' => CallTool::class,
        'resources/list' => ListResources::class,
        'resources/read' => ReadResource::class,
        'resources/templates/list' => ListResourceTemplates::class,
        'prompts/list' => ListPrompts::class,
        'prompts/get' => GetPrompt::class,
        'completion/complete' => CompletionComplete::class,
        'server/discover' => Discover::class,
        'subscriptions/listen' => Listen::class,
    ];

    public function __construct(
        protected Transport $transport,
    ) {
        //
    }

    /**
     * Add or modify a server capability.
     *
     * Using dot notation like "feature.enabled" will create a nested capability array.
     * Passing a single key like "anotherFeature" will register an empty object capability.
     */
    public function addCapability(string $key, bool $value = true): void
    {
        if (str_contains($key, '.')) {
            [$root, $child] = explode('.', $key, 2);
            $existing = $this->capabilities[$root] ?? [];

            if (! is_array($existing)) {
                $existing = [];
            }

            $existing[$child] = $value;
            $this->capabilities[$root] = $existing;

            return;
        }

        // Represent empty capability as an object when JSON encoded
        $this->capabilities[$key] = (object) [];
    }

    /**
     * Register a custom JSON-RPC method handler.
     *
     * @param  class-string<Method>  $handler
     */
    public function addMethod(string $method, string $handler): void
    {
        $this->methods[$method] = $handler;
    }

    public function start(): void
    {
        $this->boot();
        $this->detectUiCapability();

        $this->transport->onReceive($this->handle(...));
    }

    protected function boot(): void
    {
        //
    }

    public function handle(string $rawMessage): void
    {
        $context = $this->createContext();
        $requestId = null;

        try {
            $jsonRequest = json_decode($rawMessage, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new JsonRpcException('Parse error: Invalid JSON was received by the server.', ErrorCode::PARSE_ERROR->value);
            }

            $request = isset($jsonRequest['id'])
                ? JsonRpcRequest::from($jsonRequest)
                : JsonRpcNotification::from($jsonRequest);

            if ($request instanceof JsonRpcNotification) {
                return;
            }

            $requestId = $request->id;

            if ($request->method === 'initialize' && ! isset($this->methods['initialize'])) {
                throw new JsonRpcException(
                    'The [initialize] handshake was removed in MCP '.ProtocolVersion::LATEST->value.'. Send the protocol version in the request [_meta] instead.',
                    ErrorCode::METHOD_NOT_FOUND->value,
                    $request->id,
                    ['supported' => $context->supportedProtocolVersions],
                );
            }

            $this->validateProtocolMeta($request, $context);

            if (! isset($this->methods[$request->method])) {
                throw new JsonRpcException(
                    "The method [{$request->method}] was not found.",
                    ErrorCode::METHOD_NOT_FOUND->value,
                    $request->id,
                );
            }

            $this->handleMessage($request, $context);
        } catch (JsonRpcException $e) {
            $this->send($e->toJsonRpcResponse(), $context);
        } catch (Throwable $e) {
            report($e);

            $config = Container::getInstance()->make('config');

            if ($config->get('app.debug', false)) {
                throw $e;
            }

            $this->send(JsonRpcResponse::error(
                $requestId,
                ErrorCode::INTERNAL_ERROR->value,
                'Something went wrong while processing the request.',
            ), $context);
        }
    }

    public function createContext(): ServerContext
    {
        $name = $this->resolveAttribute(Name::class);
        $version = $this->resolveAttribute(Version::class);
        $instructions = $this->resolveAttribute(Instructions::class);

        return new ServerContext(
            supportedProtocolVersions: $this->supportedProtocolVersion ?: ProtocolVersion::serverSupported(),
            serverCapabilities: $this->resolvedCapabilities(),
            implementation: new Implementation(
                name: $name !== null ? $name->value : $this->name,
                version: $version !== null ? $version->value : $this->version,
                icons: $this->resolvedIcons(),
            ),
            instructions: $instructions !== null ? $instructions->value : $this->instructions,
            maxPaginationLength: $this->maxPaginationLength,
            defaultPaginationLength: $this->defaultPaginationLength,
            tools: $this->tools,
            resources: $this->resources,
            prompts: $this->prompts,
        );
    }

    /**
     * @return list<Icon>
     */
    protected function icons(): array
    {
        return [];
    }

    /**
     * @throws JsonRpcException
     */
    protected function validateProtocolMeta(JsonRpcRequest $request, ServerContext $context): void
    {
        $meta = $request->meta() ?? [];

        $expected = [
            MetaKey::PROTOCOL_VERSION->value => 'is_string',
            MetaKey::CLIENT_CAPABILITIES->value => fn (mixed $value): bool => is_array($value) && ($value === [] || ! array_is_list($value)),
        ];

        foreach ($expected as $metaKey => $isValid) {
            if (! array_key_exists($metaKey, $meta) || ! $isValid($meta[$metaKey])) {
                throw new JsonRpcException(
                    "Invalid params: The request [_meta] is missing the required [{$metaKey}] member.",
                    ErrorCode::INVALID_PARAMS->value,
                    $request->id,
                );
            }
        }

        $requestedVersion = $meta[MetaKey::PROTOCOL_VERSION->value];

        if (! in_array($requestedVersion, $context->supportedProtocolVersions, true)) {
            throw new JsonRpcException(
                'Unsupported protocol version',
                ErrorCode::UNSUPPORTED_PROTOCOL_VERSION->value,
                $request->id,
                [
                    'supported' => $context->supportedProtocolVersions,
                    'requested' => $requestedVersion,
                ],
            );
        }
    }

    /**
     * @throws JsonRpcException
     */
    protected function handleMessage(JsonRpcRequest $request, ServerContext $context): void
    {
        $response = $this->runMethodHandle($request, $context);

        if (! is_iterable($response)) {
            $this->send($response, $context, $request);

            return;
        }

        $this->transport->stream(function () use ($request, $response, $context): void {
            foreach ($response as $message) {
                $this->send($message, $context, $request);
            }
        });
    }

    protected function send(JsonRpcResponse $response, ServerContext $context, ?JsonRpcRequest $request = null): void
    {
        if ($request instanceof JsonRpcRequest && array_key_exists('result', $response->content)) {
            $result = (array) $response->content['result'];
            $result['_meta'][MetaKey::SERVER_INFO->value] = $context->implementation->toArray();

            $response->content['result'] = [
                'resultType' => 'complete',
                ...$this->resolveCacheHints($request, $context),
                ...$result,
            ];
        }

        $this->transport->send($response->toJson());
    }

    /**
     * @return array<string, Cacheable>
     */
    protected function cacheHints(): array
    {
        return [];
    }

    /**
     * @return array<string, int|string>
     */
    protected function resolveCacheHints(JsonRpcRequest $request, ServerContext $context): array
    {
        if (! in_array($request->method, self::CACHEABLE_METHODS, true)) {
            return [];
        }

        if (isset($request->params['inputResponses']) || isset($request->params['requestState'])) {
            return [];
        }

        $cacheable = $this->resourceCacheable($request, $context)
            ?? $this->cacheHints()[$request->method]
            ?? $this->resolveAttribute(Cacheable::class)
            ?? new Cacheable;

        return $cacheable->toArray();
    }

    protected function resourceCacheable(JsonRpcRequest $request, ServerContext $context): ?Cacheable
    {
        if ($request->method !== 'resources/read') {
            return null;
        }

        try {
            return $this->resolveResource($request->get('uri'), $context)->cacheable();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws JsonRpcException
     */
    protected function runMethodHandle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        $container = Container::getInstance();

        /** @var Method $methodClass */
        $methodClass = $container->make(
            $this->methods[$request->method],
        );

        $container->instance('mcp.request', $request->toRequest());

        try {
            $response = $methodClass->handle($request, $context);
        } finally {
            $container->forgetInstance('mcp.request');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvedCapabilities(): array
    {
        $extensions = Arr::mapWithKeys(
            $this->extensions,
            fn (Extension $extension): array => [$extension->value => (object) []],
        );

        return $extensions === []
            ? $this->capabilities
            : [...$this->capabilities, 'extensions' => $extensions];
    }

    protected function detectUiCapability(): void
    {
        if (collect($this->resources)->contains(fn (Resource|string $resource): bool => is_subclass_of($resource, AppResource::class))) {
            $this->extensions[] = Extension::Ui;
        }
    }

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): PendingTestResponse|TestResponse|TestListResponse
    {
        $pendingTestResponse = new PendingTestResponse(
            Container::getInstance(),
            static::class,
        );

        return $pendingTestResponse->$name(...$arguments);
    }
}
