<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class ToolSearch
{
    protected int $maxToolCalls;

    protected int $maxOutputBytes;

    /**
     * @var array<int, Tool|string>
     */
    protected array $tools = [];

    /**
     * @param  iterable<Tool|string>  $tools
     */
    public function __construct(iterable $tools)
    {
        $config = Container::getInstance()->make('config');

        $this->maxToolCalls = max(1, (int) $config->get('mcp.tool_search.max_tool_calls', 25));
        $this->maxOutputBytes = max(256, (int) $config->get('mcp.tool_search.max_output_bytes', 65_536));

        foreach ($tools as $tool) {
            if (! $tool instanceof Tool && (! is_string($tool) || ! is_subclass_of($tool, Tool::class))) {
                throw new InvalidArgumentException('ToolSearch entries must be tool instances or tool class names.');
            }

            $this->tools[] = $tool;
        }
    }

    /**
     * @return array{SearchTools, ExecuteTools}
     */
    public function tools(): array
    {
        return [new SearchTools($this), new ExecuteTools($this, $this->maxToolCalls)];
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, int $limit): array
    {
        $terms = $this->terms($query);

        $candidates = $this->resolvedTools()
            ->filter(fn (Tool $tool): bool => $tool->eligibleForRegistration())
            ->values()
            ->map(function (Tool $tool, int $index) use ($terms): array {
                $definition = $tool->toArray();
                $schema = $definition['inputSchema'] ?? ['type' => 'object', 'properties' => (object) []];
                $name = mb_strtolower($tool->name());
                $description = mb_strtolower($tool->description());
                $schemaText = mb_strtolower(json_encode($schema, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
                $score = $terms !== [] && $terms === $this->terms($name) ? 8 : 0;

                foreach ($terms as $term) {
                    $score += str_contains($name, $term) ? 4 : 0;
                    $score += str_contains($description, $term) ? 2 : 0;
                    $score += str_contains($schemaText, $term) ? 1 : 0;
                }

                $annotations = $definition['annotations'] ?? [];

                return [
                    'index' => $index,
                    'score' => $score,
                    'tool' => [
                        'name' => $tool->name(),
                        'description' => $tool->description(),
                        'inputSchema' => $schema,
                        ...is_array($annotations) && $annotations !== [] ? ['annotations' => $annotations] : [],
                    ],
                ];
            })
            ->filter(fn (array $candidate): bool => $terms === [] || $candidate['score'] > 0)
            ->sort(fn (array $left, array $right): int => $right['score'] <=> $left['score'] ?: $left['index'] <=> $right['index'])
            ->values();

        $tools = [];
        $hasMore = $candidates->count() > $limit;
        $size = $this->outputSize(['ok' => true, 'tools' => [], 'hasMore' => false]);

        foreach ($candidates->take($limit) as $candidate) {
            $size += $this->outputSize($candidate['tool']) + 1;

            if ($size > $this->maxOutputBytes) {
                if ($tools === []) {
                    return $this->outputLimitExceeded();
                }

                $hasMore = true;

                break;
            }

            $tools[] = $candidate['tool'];
        }

        return ['ok' => true, 'tools' => $tools, 'hasMore' => $hasMore];
    }

    /**
     * @param  array<int, array{name: string, arguments?: array<string, mixed>}>  $calls
     * @return array<int, Response>
     */
    public function execute(array $calls, Request $parentRequest): array
    {
        $results = [];
        $responses = [];
        $tools = $this->resolvedTools();
        $size = $this->outputSize(['ok' => true, 'results' => []]);

        foreach ($calls as $index => $call) {
            $invocation = $this->invokeTool($tools, $call['name'], $call['arguments'] ?? [], $parentRequest, $index);
            $result = $invocation['result'];
            array_push($responses, ...$invocation['notifications']);
            $results[] = $entry = ['name' => $call['name'], ...$result];
            $size += $this->outputSize($entry) + 1;

            if ($size > $this->maxOutputBytes) {
                return [...$responses, $this->response($this->outputLimitExceeded(count($results), $index + 1), true)];
            }

            if ($result['isError']) {
                return [...$responses, $this->response(['ok' => false, 'results' => $results], true)];
            }
        }

        return [...$responses, $this->response(['ok' => true, 'results' => $results])];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function response(array $content, bool $isError = false): Response
    {
        $json = json_encode($content, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $isError ? Response::error($json) : Response::text($json);
    }

    /**
     * @param  Collection<int, Tool>  $tools
     * @param  array<string, mixed>  $arguments
     * @return array{notifications: array<int, Response>, result: array<string, mixed>}
     */
    protected function invokeTool(Collection $tools, string $name, array $arguments, Request $parentRequest, int $index): array
    {
        $params = ['name' => $name, 'arguments' => $arguments];

        if ($parentRequest->meta() !== null) {
            $params['_meta'] = $parentRequest->meta();
        }

        $request = new JsonRpcRequest(
            id: "execute-tools:{$index}",
            method: 'tools/call',
            params: $params,
            sessionId: $parentRequest->sessionId(),
        );

        $container = Container::getInstance();
        $hadParentRequest = $container->bound('mcp.request');
        $boundParentRequest = $hadParentRequest ? $container->make('mcp.request') : null;
        $container->instance('mcp.request', $request->toRequest());

        try {
            $tool = $tools->first(fn (Tool $tool): bool => $tool->name() === $name);

            if (! $tool instanceof Tool) {
                return ['notifications' => [], 'result' => $this->failedResult("Tool [{$name}] was not found in the catalog.")];
            }

            if (! $tool->eligibleForRegistration()) {
                return ['notifications' => [], 'result' => $this->failedResult("Tool [{$name}] is not available.")];
            }

            $toolResponses = (new ToolInvoker)->invoke($tool, $request);
            $toolResponses = $toolResponses instanceof JsonRpcResponse ? [$toolResponses] : $toolResponses;
            $notifications = [];
            $result = null;

            foreach ($toolResponses as $toolResponse) {
                $payload = $toolResponse->toArray();

                if (isset($payload['method'])) {
                    $notifications[] = Response::notification($payload['method'], is_array($payload['params']) ? $payload['params'] : []);

                    continue;
                }

                $result = $payload['result'];
            }

            return [
                'notifications' => $notifications,
                'result' => $result ?? $this->failedResult("Tool [{$name}] returned no result."),
            ];
        } finally {
            if ($hadParentRequest) {
                $container->instance('mcp.request', $boundParentRequest);
            } else {
                $container->forgetInstance('mcp.request');
            }
        }
    }

    /**
     * @return Collection<int, Tool>
     */
    protected function resolvedTools(): Collection
    {
        $tools = collect($this->tools)->map(fn (Tool|string $tool): Tool => is_string($tool)
            ? Container::getInstance()->make($tool)
            : $tool);

        $duplicate = $tools->map(fn (Tool $tool): string => $tool->name())->duplicates()->first();

        if (is_string($duplicate)) {
            throw new InvalidArgumentException("Duplicate tool name [{$duplicate}] in ToolSearch catalog.");
        }

        return $tools->values();
    }

    /**
     * @return array<int, string>
     */
    protected function terms(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[^\pL\pN]+/u', mb_strtolower($text)) ?: [],
            fn (string $term): bool => $term !== '',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function failedResult(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function outputLimitExceeded(int $completedToolCalls = 0, int $attemptedToolCalls = 0): array
    {
        return [
            'ok' => false,
            'error' => [
                'kind' => 'OutputLimitExceeded',
                'message' => "The tool output exceeded {$this->maxOutputBytes} bytes.",
            ],
            'completedToolCalls' => $completedToolCalls,
            'attemptedToolCalls' => $attemptedToolCalls,
        ];
    }

    /**
     * @param  array<string, mixed>  $output
     */
    protected function outputSize(array $output): int
    {
        try {
            return strlen(json_encode($output, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException("Unable to encode the tool output: {$jsonException->getMessage()}", 0, $jsonException);
        }
    }
}
