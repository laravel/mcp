<?php

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Completions\CompletionHelper;
use Laravel\Mcp\Server\Completions\CompletionResponse;
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

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        if ($argument !== 'language') {
            return CompletionResponse::empty();
        }

        $languages = ['php', 'python', 'javascript', 'typescript', 'go', 'rust'];
        $matches = CompletionHelper::filterByPrefix($languages, $value);

        return CompletionResponse::from($matches);
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

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        return match ($argument) {
            'projectId' => CompletionResponse::from(['project-1', 'project-2', 'project-3']),
            'taskId' => $this->completeTaskId($context),
            default => CompletionResponse::empty(),
        };
    }

    protected function completeTaskId(array $context): CompletionResponse
    {
        $projectId = $context['projectId'] ?? null;

        if (! $projectId) {
            return CompletionResponse::empty();
        }

        $tasks = [
            'project-1' => ['task-1-1', 'task-1-2'],
            'project-2' => ['task-2-1', 'task-2-2'],
            'project-3' => ['task-3-1', 'task-3-2'],
        ];

        return CompletionResponse::from($tasks[$projectId] ?? []);
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

    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        return match ($argument) {
            'userId' => CompletionResponse::from(['user-1', 'user-2', 'user-3']),
            'fileId' => $this->completeFileId($context),
            default => CompletionResponse::empty(),
        };
    }

    protected function completeFileId(array $context): CompletionResponse
    {
        $userId = $context['userId'] ?? null;

        if (! $userId) {
            return CompletionResponse::empty();
        }

        $files = [
            'user-1' => ['file1.txt', 'file2.txt'],
            'user-2' => ['doc1.txt', 'doc2.txt'],
            'user-3' => ['report1.txt', 'report2.txt'],
        ];

        return CompletionResponse::from($files[$userId] ?? []);
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

    it('returns empty taskId completions without project context', function (): void {
        TestCompletionServer::completion(ProjectTaskCompletionPrompt::class, 'taskId', '')
            ->assertCompletionCount(0);
    });

    it('completes taskId based on project context', function (): void {
        TestCompletionServer::completion(
            ProjectTaskCompletionPrompt::class,
            'taskId',
            '',
            ['projectId' => 'project-1']
        )
            ->assertCompletionValues(['task-1-1', 'task-1-2'])
            ->assertCompletionCount(2);
    });

    it('provides different tasks for different projects', function (): void {
        TestCompletionServer::completion(
            ProjectTaskCompletionPrompt::class,
            'taskId',
            '',
            ['projectId' => 'project-2']
        )
            ->assertCompletionValues(['task-2-1', 'task-2-2'])
            ->assertCompletionCount(2);
    });
});

describe('Resource Template Completions', function (): void {
    it('completes userId', function (): void {
        TestCompletionServer::completion(UserFileCompletionResource::class, 'userId', '')
            ->assertHasCompletions(['user-1', 'user-2', 'user-3'])
            ->assertCompletionCount(3);
    });

    it('returns empty fileId completions without user context', function (): void {
        TestCompletionServer::completion(UserFileCompletionResource::class, 'fileId', '')
            ->assertCompletionCount(0);
    });

    it('completes fileId based on user context', function (): void {
        TestCompletionServer::completion(
            UserFileCompletionResource::class,
            'fileId',
            '',
            ['userId' => 'user-1']
        )
            ->assertCompletionValues(['file1.txt', 'file2.txt'])
            ->assertCompletionCount(2);
    });

    it('provides different files for different users', function (): void {
        TestCompletionServer::completion(
            UserFileCompletionResource::class,
            'fileId',
            '',
            ['userId' => 'user-2']
        )
            ->assertCompletionValues(['doc1.txt', 'doc2.txt'])
            ->assertCompletionCount(2);
    });
});
