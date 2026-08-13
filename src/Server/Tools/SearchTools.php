<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class SearchTools extends Tool
{
    protected string $name = 'search_tools';

    protected string $title = 'Search Tools';

    public function __construct(protected ToolSearch $catalog) {}

    public function description(): string
    {
        return 'Search the tools available through execute_tools. Returns exact tool names, descriptions, and complete input schemas. An empty query browses the catalog.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(4096)->description('Search terms. An empty query browses the catalog.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum results to return. Defaults to 10.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'query' => ['nullable', 'string', 'max:4096'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $result = $this->catalog->search(
            (string) $request->get('query', ''),
            (int) $request->get('limit', 10),
        );

        return $this->catalog->response($result, ! $result['ok']);
    }
}
