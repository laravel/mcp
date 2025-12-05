<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Completions\CompletionHelper;
use Laravel\Mcp\Server\Completions\CompletionResult;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Contracts\SupportsCompletion;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class TestCompletionServer extends Server
{
    protected string $name = 'Test Completion Server';

    protected array $capabilities = [
        'completions' => [],
    ];

    protected array $prompts = [
        LanguageCompletionPrompt::class,
        ProjectTaskCompletionPrompt::class,
    ];

    protected array $resources = [
        UserFileCompletionResource::class,
    ];
}

class LanguageCompletionPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Select a programming language';

    public function arguments(): array
    {
        return [
            new Argument('language', 'Programming language', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResult
    {
        if ($argument !== 'language') {
            return CompletionResult::empty();
        }

        $languages = ['php', 'python', 'javascript', 'typescript', 'go', 'rust'];
        $matches = CompletionHelper::filterByPrefix($languages, $value);

        return CompletionResult::make($matches);
    }

    public function handle(Request $request): Response
    {
        return Response::text("Selected language: {$request->get('language')}");
    }
}

class ProjectTaskCompletionPrompt extends Prompt implements SupportsCompletion
{
    protected string $description = 'Project and task selection';

    public function arguments(): array
    {
        return [
            new Argument('projectId', 'Project ID', required: true),
            new Argument('taskId', 'Task ID', required: true),
        ];
    }

    public function complete(string $argument, string $value): CompletionResult
    {
        return match ($argument) {
            'projectId' => CompletionResult::make(['project-1', 'project-2', 'project-3']),
            'taskId' => CompletionResult::make(['task-1-1', 'task-1-2', 'task-2-1', 'task-2-2']),
            default => CompletionResult::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("Project: {$request->get('projectId')}, Task: {$request->get('taskId')}");
    }
}

class UserFileCompletionResource extends Resource implements HasUriTemplate, SupportsCompletion
{
    protected string $mimeType = 'text/plain';

    protected string $description = 'Access user files';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://users/{userId}/files/{fileId}');
    }

    public function complete(string $argument, string $value): CompletionResult
    {
        return match ($argument) {
            'userId' => CompletionResult::make(['user-1', 'user-2', 'user-3']),
            'fileId' => CompletionResult::make(['file1.txt', 'file2.txt', 'doc1.txt', 'doc2.txt']),
            default => CompletionResult::empty(),
        };
    }

    public function handle(Request $request): Response
    {
        return Response::text("User: {$request->get('userId')}, File: {$request->get('fileId')}");
    }
}

describe('Prompt Completions', function (): void {
    it('completes language argument with prefix matching', function (): void {
        TestCompletionServer::completion(LanguageCompletionPrompt::class, 'language', 'py')
            ->assertHasCompletions(['python'])
            ->assertCompletionCount(1);
    });

    it('returns all languages when no prefix provided', function (): void {
        TestCompletionServer::completion(LanguageCompletionPrompt::class, 'language', '')
            ->assertCompletionCount(6);
    });

    it('refines completions as user types', function (): void {
        // Type "j" - gets java and javascript
        TestCompletionServer::completion(LanguageCompletionPrompt::class, 'language', 'j')
            ->assertHasCompletions(['javascript'])
            ->assertCompletionCount(1);

        // Type "ja" - narrows to just javascript
        TestCompletionServer::completion(LanguageCompletionPrompt::class, 'language', 'ja')
            ->assertCompletionValues(['javascript'])
            ->assertCompletionCount(1);
    });

    it('returns empty completions for non-matching prefix', function (): void {
        TestCompletionServer::completion(LanguageCompletionPrompt::class, 'language', 'xyz')
            ->assertCompletionCount(0);
    });
});

describe('Multi-Argument Completions', function (): void {
    it('completes projectId', function (): void {
        TestCompletionServer::completion(ProjectTaskCompletionPrompt::class, 'projectId', '')
            ->assertHasCompletions(['project-1', 'project-2', 'project-3'])
            ->assertCompletionCount(3);
    });

    it('completes taskId', function (): void {
        TestCompletionServer::completion(ProjectTaskCompletionPrompt::class, 'taskId', '')
            ->assertHasCompletions(['task-1-1', 'task-1-2', 'task-2-1', 'task-2-2'])
            ->assertCompletionCount(4);
    });
});

describe('Resource Template Completions', function (): void {
    it('completes userId', function (): void {
        TestCompletionServer::completion(UserFileCompletionResource::class, 'userId', '')
            ->assertHasCompletions(['user-1', 'user-2', 'user-3'])
            ->assertCompletionCount(3);
    });

    it('completes fileId', function (): void {
        TestCompletionServer::completion(UserFileCompletionResource::class, 'fileId', '')
            ->assertHasCompletions(['file1.txt', 'file2.txt', 'doc1.txt', 'doc2.txt'])
            ->assertCompletionCount(4);
    });
});
