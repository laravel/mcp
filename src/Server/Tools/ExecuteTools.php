<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class ExecuteTools extends Tool
{
    protected string $name = 'execute_tools';

    protected string $title = 'Execute Tools';

    public function __construct(protected ToolsSearch $catalog) {}

    public function description(): string
    {
        return 'Execute multiple independent catalog tools synchronously in order and stop on the first error. Use names and arguments returned by search_tools. When a call depends on a previous result, call execute_tools multiple times.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'calls' => $schema->array()
                ->items($schema->object([
                    'name' => $schema->string()->max(255)->description('The exact tool name returned by search_tools.')->required(),
                    'arguments' => $schema->object()->description('Arguments matching the tool input schema.'),
                ])->withoutAdditionalProperties())
                ->min(1)
                ->max($this->catalog->maxToolCallsValue())
                ->description('Independent tool calls to execute synchronously in order.')
                ->required(),
        ];
    }

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $request->validate([
            'calls' => ['required', 'array', 'min:1', 'max:'.$this->catalog->maxToolCallsValue()],
            'calls.*' => ['array:name,arguments'],
            'calls.*.name' => ['required', 'string', 'max:255'],
            'calls.*.arguments' => ['array'],
        ]);

        $calls = $request->get('calls');

        if (! is_array($calls) || ! array_is_list($calls)) {
            throw ValidationException::withMessages(['calls' => 'The calls field must be a list.']);
        }

        foreach ($calls as $index => $call) {
            $arguments = $call['arguments'] ?? [];

            if ($arguments !== [] && array_is_list($arguments)) {
                throw ValidationException::withMessages(["calls.{$index}.arguments" => 'The arguments field must be an object.']);
            }
        }

        return $this->catalog->execute($calls, $request);
    }
}
