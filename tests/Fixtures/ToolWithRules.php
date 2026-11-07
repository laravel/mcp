<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ToolWithRules extends Tool
{
    protected string $description = 'This tool greets a person a number of times';

    public function handle(Request $request): Response
    {
        return Response::text(str_repeat('Hello, '.$request->get('name').'! ', $request->get('times', 1)));
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:20',
            'times' => ['integer', Rule::in([1, 2, 3])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tell me who to greet.',
        ];
    }
}
