<?php

declare(strict_types=1);

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Schema\Icon;
use Laravel\Mcp\Server\Attributes\Icon as IconAttribute;
use Laravel\Mcp\Server\Content\Audio;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Image;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\ResourceLink;
use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Resource;
use Tests\Fixtures\AnnotatedResource;
use Tests\Fixtures\DailyPlanResource;

it('creates a notification response', function (): void {
    $response = Response::notification('test.method', ['key' => 'value']);

    expect($response->content())->toBeInstanceOf(Notification::class)
        ->and($response->isNotification())->toBeTrue()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a text response', function (): void {
    $response = Response::text('Hello world');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates a blob response', function (): void {
    $response = Response::blob('binary content');

    expect($response->content())->toBeInstanceOf(Blob::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an error response', function (): void {
    $response = Response::error('Something went wrong');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeTrue()
        ->and($response->role())->toBe(Role::User);
});

it('creates an image response', function (): void {
    $response = Response::image('raw-bytes');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an image response with custom mime type', function (): void {
    $response = Response::image('raw-bytes', 'image/webp');

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray()['mimeType'])->toBe('image/webp');
});

it('creates an audio response', function (): void {
    $response = Response::audio('raw-bytes');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);
});

it('creates an audio response with custom mime type', function (): void {
    $response = Response::audio('raw-bytes', 'audio/mp3');

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->content()->toArray()['mimeType'])->toBe('audio/mp3');
});

it('creates image response with content meta', function (): void {
    $response = Response::image('raw-bytes')->withMeta(['source' => 'camera']);

    expect($response->content())->toBeInstanceOf(Image::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['source' => 'camera']);
});

it('creates audio response with content meta', function (): void {
    $response = Response::audio('raw-bytes')->withMeta(['duration' => '3.5s']);

    expect($response->content())->toBeInstanceOf(Audio::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['duration' => '3.5s']);
});

it('can convert response to assistant role', function (): void {
    $response = Response::text('Original message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class)
        ->and($assistantResponse->role())->toBe(Role::Assistant)
        ->and($assistantResponse->isError())->toBeFalse();
});

it('preserves error state when converting to assistant role', function (): void {
    $response = Response::error('Error message');
    $assistantResponse = $response->asAssistant();

    expect($assistantResponse->content())->toBeInstanceOf(Text::class)
        ->and($assistantResponse->role())->toBe(Role::Assistant)
        ->and($assistantResponse->isError())->toBeTrue();
});

it('creates a json response', function (): void {
    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->isNotification())->toBeFalse()
        ->and($response->isError())->toBeFalse()
        ->and($response->role())->toBe(Role::User);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR));
});

it('handles nested arrays in json response', function (): void {
    $data = [
        'user' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'roles' => ['admin', 'developer'],
        ],
        'active' => true,
    ];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR));
});

it('throws JsonException for invalid json data', function (): void {
    $data = ['invalid' => INF];

    expect(function () use ($data): void {
        Response::json($data);
    })->toThrow(JsonException::class);
});

it('handles empty array in json response', function (): void {
    $data = [];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = $response->content();
    expect((string) $content)->toBe(json_encode($data, JSON_THROW_ON_ERROR));
});

it('creates text response with content meta', function (): void {
    $response = Response::text('Hello')->withMeta(['author' => 'John']);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['author' => 'John']);
});

it('creates blob response with content meta', function (): void {
    $response = Response::blob('binary')->withMeta(['encoding' => 'utf-8']);

    expect($response->content())->toBeInstanceOf(Blob::class)
        ->and($response->content()->toArray())->toHaveKey('_meta')
        ->and($response->content()->toArray()['_meta'])->toEqual(['encoding' => 'utf-8']);
});

it('creates a notification response with content meta', function (): void {
    $response = Response::notification('test/event', ['data' => 'value'])->withMeta(['author' => 'system']);

    expect($response->content())->toBeInstanceOf(Notification::class)
        ->and($response->content()->toArray()['params'])->toHaveKey('_meta')
        ->and($response->content()->toArray()['params']['_meta'])->toEqual(['author' => 'system']);
});

it('throws exception when array contains a non-Response object', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::text('Valid'),
        'Invalid string',
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when array contains nested ResponseFactory', function (): void {
    $nestedFactory = Response::make(Response::text('Nested'));

    expect(fn (): ResponseFactory => Response::make([
        Response::text('First'),
        $nestedFactory,
        Response::text('Third'),
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('throws exception when an array contains null', function (): void {
    expect(fn (): ResponseFactory => Response::make([
        Response::text('Valid'),
        null,
    ]))->toThrow(
        InvalidArgumentException::class,
    );
});

it('reads html content from a relative file path', function (): void {
    $dir = resource_path();

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir.'/test-response.html', '<html><body>From File</body></html>');

    $response = Response::html('test-response.html');

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and((string) $response->content())->toBe('<html><body>From File</body></html>');

    @unlink($dir.'/test-response.html');
});

it('reads html content from an absolute file path', function (): void {
    $path = sys_get_temp_dir().'/mcp-test-absolute.html';

    file_put_contents($path, '<div>Absolute</div>');

    $response = Response::html($path);

    expect((string) $response->content())->toBe('<div>Absolute</div>');

    @unlink($path);
});

it('creates a response from a blade view', function (): void {
    $viewDir = resource_path('views');

    if (! is_dir($viewDir)) {
        mkdir($viewDir, 0755, true);
    }

    file_put_contents($viewDir.'/mcp-view-test.blade.php', '<p>{{ $name }}</p>');

    $response = Response::view('mcp-view-test', ['name' => 'Test']);

    expect($response->content())->toBeInstanceOf(Text::class)
        ->and((string) $response->content())->toContain('<p>Test</p>')
        ->and($response->isError())->toBeFalse();

    @unlink($viewDir.'/mcp-view-test.blade.php');
});

it('throws exception for missing html file', function (): void {
    Response::html('/nonexistent/path/missing.html');
})->throws(InvalidArgumentException::class, 'File not found at path [/nonexistent/path/missing.html].');

it('creates compact json response', function (): void {
    $data = ['key' => 'value', 'number' => 123];
    $response = Response::json($data);

    expect($response->content())->toBeInstanceOf(Text::class);

    $content = (string) $response->content();
    expect($content)->toBe('{"key":"value","number":123}')
        ->and($content)->not->toContain("\n");
});

it('creates a resource link from a uri string', function (): void {
    $response = Response::resourceLink(
        'file:///data/report.json',
        'Monthly Report',
        'application/json',
        description: 'Generated sales report',
    );

    expect($response->content()->toArray())->toEqual([
        'type' => 'resource_link',
        'uri' => 'file:///data/report.json',
        'name' => 'Monthly Report',
        'description' => 'Generated sales report',
        'mimeType' => 'application/json',
    ]);
});

it('requires a name when creating a resource link from a uri string', function (): void {
    expect(fn (): Response => Response::resourceLink('file:///data/report.json'))
        ->toThrow(InvalidArgumentException::class, 'Resource link name is required when using a URI string.');
});

it('creates a resource link from a Resource class-string', function (): void {
    $response = Response::resourceLink(DailyPlanResource::class);

    $payload = $response->content()->toArray();

    expect($payload['type'])->toBe('resource_link')
        ->and($payload['uri'])->toBe((new DailyPlanResource)->uri())
        ->and($payload['name'])->toBe((new DailyPlanResource)->name())
        ->and($payload['mimeType'])->toBe((new DailyPlanResource)->mimeType());
});

it('creates a resource link from a Resource instance', function (): void {
    $resource = new DailyPlanResource;
    $response = Response::resourceLink($resource);

    expect($response->content()->toArray()['uri'])->toBe($resource->uri());
});

it('inherits annotations from a Resource', function (): void {
    $response = Response::resourceLink(AnnotatedResource::class);

    expect($response->content()->toArray()['annotations'])->toEqual([
        'audience' => ['user'],
        'priority' => 0.7,
        'lastModified' => '2026-05-01T00:00:00Z',
    ]);
});

it('wraps a pre-built ResourceLink instance', function (): void {
    $link = new ResourceLink(
        'file:///data/report.json',
        'Monthly Report',
        annotations: [
            'audience' => ['user', 'assistant'],
            'priority' => 0.9,
            'lastModified' => '2026-05-07T12:00:00Z',
        ],
    );

    $response = Response::resourceLink($link);

    expect($response->content())->toBe($link)
        ->and($response->content()->toArray())->toEqual([
            'type' => 'resource_link',
            'uri' => 'file:///data/report.json',
            'name' => 'Monthly Report',
            'annotations' => [
                'audience' => ['user', 'assistant'],
                'priority' => 0.9,
                'lastModified' => '2026-05-07T12:00:00Z',
            ],
        ]);
});

it('allows overriding fields when given a Resource', function (): void {
    $response = Response::resourceLink(
        DailyPlanResource::class,
        title: 'Custom Title',
        size: 4096,
    );

    $payload = $response->content()->toArray();

    expect($payload['title'])->toBe('Custom Title')
        ->and($payload['size'])->toBe(4096);
});

it('inherits icons from a Resource when none are provided', function (): void {
    $resource = new class extends Resource
    {
        protected string $uri = 'file://resources/iconic';

        public function handle(): string
        {
            return 'content';
        }

        public function icons(): array
        {
            return [new Icon('https://example.com/resource.png', mimeType: 'image/png')];
        }
    };

    $response = Response::resourceLink($resource);

    expect($response->content()->toArray()['icons'])->toBe([
        ['src' => 'https://example.com/resource.png', 'mimeType' => 'image/png'],
    ]);
});

it('inherits Icon attributes from a Resource when none are provided', function (): void {
    $response = Response::resourceLink(new ResponseResourceWithIconAttribute);

    expect($response->content()->toArray()['icons'])->toBe([
        ['src' => 'https://example.com/attribute-resource.png', 'mimeType' => 'image/png'],
    ]);
});

it('lets explicit icons override the Resource icons', function (): void {
    $resource = new class extends Resource
    {
        protected string $uri = 'file://resources/iconic';

        public function handle(): string
        {
            return 'content';
        }

        public function icons(): array
        {
            return [new Icon('https://example.com/inherited.png')];
        }
    };

    $response = Response::resourceLink(
        $resource,
        icons: [new Icon('https://example.com/override.png', mimeType: 'image/png')],
    );

    expect($response->content()->toArray()['icons'])->toBe([
        ['src' => 'https://example.com/override.png', 'mimeType' => 'image/png'],
    ]);
});

it('attaches icons to a resource link built from a URI string', function (): void {
    $response = Response::resourceLink(
        'https://example.com/data.json',
        name: 'Dataset',
        icons: [new Icon('https://example.com/data.png', mimeType: 'image/png')],
    );

    expect($response->content()->toArray()['icons'])->toBe([
        ['src' => 'https://example.com/data.png', 'mimeType' => 'image/png'],
    ]);
});

#[IconAttribute('https://example.com/attribute-resource.png', mimeType: 'image/png')]
class ResponseResourceWithIconAttribute extends Resource
{
    protected string $uri = 'file://resources/icon-attribute';

    public function handle(): string
    {
        return 'content';
    }
}
