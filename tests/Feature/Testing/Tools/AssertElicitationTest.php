<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Elicitation\Elicitation;
use Laravel\Mcp\Server\Elicitation\ElicitSchema;
use Laravel\Mcp\Server\Elicitation\UrlElicitationRequiredException;
use Laravel\Mcp\Server\Tool;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;

class ElicitServer extends Server
{
    protected array $tools = [
        FormTool::class,
        UrlTool::class,
        NoElicitTool::class,
        UrlRequiredTool::class,
    ];
}

class FormTool extends Tool
{
    protected string $description = 'Asks for a name via form elicitation.';

    public function handle(Request $request, Elicitation $elicitation): Response
    {
        $result = $elicitation->form('What is your name?', fn (ElicitSchema $schema): array => [
            'name' => $schema->string('Your Name')->required(),
        ]);

        if ($result->accepted()) {
            return Response::text('Hello, '.$result->get('name').'!');
        }

        return Response::text('No name provided.');
    }
}

class UrlTool extends Tool
{
    protected string $description = 'Asks user to authorize via URL.';

    public function handle(Request $request, Elicitation $elicitation): Response
    {
        $result = $elicitation->url(
            'Please authorize access',
            'https://example.com/oauth',
        );

        if ($result->accepted()) {
            $elicitation->notifyComplete($result->elicitationId());

            return Response::text('Authorization complete.');
        }

        return Response::text('Authorization declined.');
    }
}

class NoElicitTool extends Tool
{
    protected string $description = 'A tool that does not elicit.';

    public function handle(Request $request): Response
    {
        return Response::text('No elicitation needed.');
    }
}

class UrlRequiredTool extends Tool
{
    protected string $description = 'Throws UrlElicitationRequiredException.';

    public function handle(Request $request): Response
    {
        throw new UrlElicitationRequiredException('OAuth required', [
            ['mode' => 'url', 'url' => 'https://example.com/oauth'],
        ]);
    }
}

// Form elicitation tests

it('may elicit a form and assert the response', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']])
        ->tool(FormTool::class);

    $response->assertSee('Hello, Taylor!');
});

it('may assert a form elicitation was sent', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']])
        ->tool(FormTool::class);

    $response->assertElicited()
        ->assertElicitedForm('What is your name?')
        ->assertElicitationCount(1);
});

it('may assert a declined form elicitation', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'decline'])
        ->tool(FormTool::class);

    $response->assertSee('No name provided.');
});

// URL elicitation tests

it('may elicit a url and assert the response', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept'])
        ->tool(UrlTool::class);

    $response->assertSee('Authorization complete.');
});

it('may assert a url elicitation was sent', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept'])
        ->tool(UrlTool::class);

    $response->assertElicited()
        ->assertElicitedUrl('Please authorize access', 'https://example.com/oauth');
});

it('may assert a declined url elicitation', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'decline'])
        ->tool(UrlTool::class);

    $response->assertSee('Authorization declined.');
});

// No elicitation tests

it('may assert no elicitation was sent', function (): void {
    $response = ElicitServer::tool(NoElicitTool::class);

    $response->assertNotElicited()
        ->assertSee('No elicitation needed.');
});

// UrlElicitationRequiredException tests

it('may catch url elicitation required exception', function (): void {
    $response = ElicitServer::tool(UrlRequiredTool::class);

    $response->assertSee('OAuth required');
});

// Failure assertion tests

it('fails when asserting elicited but none were sent', function (): void {
    $response = ElicitServer::tool(NoElicitTool::class);

    $response->assertElicited();
})->throws(ExpectationFailedException::class);

it('fails when asserting not elicited but some were sent', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']])
        ->tool(FormTool::class);

    $response->assertNotElicited();
})->throws(ExpectationFailedException::class);

it('fails when asserting wrong form message', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']])
        ->tool(FormTool::class);

    $response->assertElicitedForm('Wrong message');
})->throws(AssertionFailedError::class);

it('fails when asserting wrong elicitation count', function (): void {
    $response = ElicitServer::withElicitation()
        ->expectsElicitation(['action' => 'accept', 'content' => ['name' => 'Taylor']])
        ->tool(FormTool::class);

    $response->assertElicitationCount(5);
})->throws(ExpectationFailedException::class);
