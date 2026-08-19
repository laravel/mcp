<?php

declare(strict_types=1);

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Enums\MetaKey;
use Laravel\Mcp\Exceptions\ElicitationNotSupportedException;
use Laravel\Mcp\Exceptions\InputRequiredException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Elicitations\ElicitResponse;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class ElicitationServer extends Server
{
    protected array $tools = [
        ElicitationTool::class,
        StreamingElicitationTool::class,
        UrlElicitationTool::class,
        MultiRoundElicitationTool::class,
    ];

    protected array $prompts = [ElicitationPrompt::class];

    protected array $resources = [ElicitationResource::class];
}

class ElicitationTool extends Tool
{
    public function handle(Request $request): Response
    {
        $response = $request->ask('Your GitHub username', fn (JsonSchema $schema): array => [
            'name' => $schema->string()->required(),
        ]);

        if ($response->declined()) {
            return Response::error('Declined');
        }

        if ($response->cancelled()) {
            return Response::error('Cancelled');
        }

        return Response::text("Hi {$response['name']}");
    }
}

class StreamingElicitationTool extends Tool
{
    public function handle(Request $request): Generator
    {
        $response = $request->ask('Choose a release', [
            'type' => 'object',
            'properties' => ['release' => ['type' => 'string']],
            'required' => ['release'],
        ]);

        yield Response::text("Release {$response['release']}");
    }
}

class MultiRoundElicitationTool extends Tool
{
    public function handle(Request $request): Response
    {
        $schema = fn (JsonSchema $schema): array => ['value' => $schema->string()->required()];

        $first = $request->ask('First value', $schema, 'first');
        $second = $request->ask('Second value', $schema, 'second');

        return Response::text("{$first['value']} then {$second['value']}");
    }
}

class UrlElicitationTool extends Tool
{
    public function handle(Request $request): Response
    {
        $response = $request->elicitUrl('Sign in to continue', 'https://example.com/sign-in');

        return Response::text($response->accepted() ? 'Signed in' : 'Not signed in');
    }
}

class ElicitationPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $response = $request->ask('Pick a topic', [
            'type' => 'object',
            'properties' => ['topic' => ['type' => 'string']],
        ]);

        return Response::text("Explain {$response['topic']}");
    }
}

class ElicitationResource extends Resource
{
    public function handle(Request $request): Response
    {
        $response = $request->ask('Pick a locale', [
            'type' => 'object',
            'properties' => ['locale' => ['type' => 'string']],
        ]);

        return Response::text("Locale {$response['locale']}");
    }
}

function elicitationMeta(array $elicitation = ['form' => [], 'url' => []]): array
{
    return [
        MetaKey::CLIENT_CAPABILITIES->value => ['elicitation' => $elicitation],
    ];
}

it('elicits and accepts form input', function (): void {
    ElicitationServer::tool(ElicitationTool::class)
        ->assertInputRequired()
        ->assertElicits('Your GitHub username')
        ->respond(['name' => 'octocat'])
        ->assertSee('Hi octocat')
        ->assertOk();
});

it('exposes declined and cancelled form input', function (string $action, string $message): void {
    ElicitationServer::tool(ElicitationTool::class)
        ->respond([], $action)
        ->assertHasErrors([$message]);
})->with([
    ['decline', 'Declined'],
    ['cancel', 'Cancelled'],
]);

it('elicits URL input and can elicit it on a new call', function (): void {
    ElicitationServer::tool(UrlElicitationTool::class)
        ->assertInputRequired()
        ->assertElicits('Sign in to continue')
        ->respond(null)
        ->assertSee('Signed in');

    ElicitationServer::tool(UrlElicitationTool::class)->assertInputRequired();
});

it('keeps earlier answers across elicitation rounds', function (): void {
    ElicitationServer::tool(MultiRoundElicitationTool::class)
        ->assertElicits('First value')
        ->respond(['value' => 'one'])
        ->assertElicits('Second value')
        ->respond(['value' => 'two'])
        ->assertSee('one then two');
});

it('supports elicitation from generators', function (): void {
    ElicitationServer::tool(StreamingElicitationTool::class)
        ->assertInputRequired()
        ->respond(['release' => 'v2.0.0'])
        ->assertSee('Release v2.0.0');
});

it('supports elicitation from prompts and resources', function (): void {
    ElicitationServer::prompt(ElicitationPrompt::class)
        ->assertInputRequired()
        ->respond(['topic' => 'MRTR'])
        ->assertSee('Explain MRTR');

    ElicitationServer::resource(ElicitationResource::class)
        ->assertInputRequired()
        ->respond(['locale' => 'en'])
        ->assertSee('Locale en');
});

it('uses stable default keys and honors explicit keys', function (): void {
    $request = new Request(meta: elicitationMeta());
    $schema = fn (JsonSchema $schema): array => ['name' => $schema->string()->required()];
    $capture = function (?string $key = null) use ($request, $schema): InputRequiredException {
        try {
            $request->ask('Name', $schema, $key);
        } catch (InputRequiredException $inputRequiredException) {
            return $inputRequiredException;
        }

        throw new RuntimeException('The request did not require input.');
    };

    $first = $capture();
    $second = $capture();
    $explicit = $capture('name');

    expect(array_key_first($first->inputRequests()))
        ->toBe(array_key_first($second->inputRequests()))
        ->not->toBe('name')
        ->and(array_key_first($explicit->inputRequests()))->toBe('name');
});

it('gates form and URL modes by client capability', function (): void {
    $legacyForm = new Request(meta: elicitationMeta([]));
    $urlOnly = new Request(meta: elicitationMeta(['url' => []]));

    expect($legacyForm->clientSupports('elicitation'))->toBeTrue()
        ->and($legacyForm->clientSupports('elicitation.form'))->toBeTrue()
        ->and($legacyForm->clientSupports('elicitation.url'))->toBeFalse()
        ->and($urlOnly->clientSupports('elicitation.form'))->toBeFalse()
        ->and($urlOnly->clientSupports('elicitation.url'))->toBeTrue()
        ->and(fn (): ElicitResponse => $legacyForm->elicitUrl('Sign in', 'https://example.com'))
        ->toThrow(ElicitationNotSupportedException::class, 'The client does not support URL elicitation.');
});

it('wraps input responses and validates accepted content', function (): void {
    $response = new ElicitResponse([
        'action' => 'accept',
        'content' => ['email' => 'octocat@example.com'],
    ]);

    expect($response->accepted())->toBeTrue()
        ->and($response->declined())->toBeFalse()
        ->and($response->cancelled())->toBeFalse()
        ->and($response->get('email'))->toBe('octocat@example.com')
        ->and($response['email'])->toBe('octocat@example.com')
        ->and($response->validate(['email' => 'required|email']))->toBe([
            'email' => 'octocat@example.com',
        ]);

    expect(fn (): array => $response->validate(['email' => 'required|url']))
        ->toThrow(ValidationException::class);

    expect(function () use ($response): void {
        $response['email'] = 'other@example.com';
    })->toThrow(LogicException::class, 'Elicitation responses are immutable.')
        ->and(function () use ($response): void {
            unset($response['email']);
        })->toThrow(LogicException::class, 'Elicitation responses are immutable.');
});
