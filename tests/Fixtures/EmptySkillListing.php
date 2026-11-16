<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Laravel\Mcp\Server\Methods\ListSkills;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

class EmptySkillListing extends ListSkills
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, ['skills' => []]);
    }
}
