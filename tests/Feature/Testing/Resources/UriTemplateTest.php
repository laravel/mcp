<?php

declare(strict_types=1);

use Tests\Fixtures\UriTemplateSummaryResource;
use Tests\Fixtures\UriTemplateTestServer;
use Tests\Fixtures\UriTemplateUserFileResource;

it('resolves URI template variables from arguments when testing resources', function (): void {
    $response = UriTemplateTestServer::resource(UriTemplateSummaryResource::class, ['id' => 'abc']);

    $response->assertOk()
        ->assertSee('"id":"abc"')
        ->assertSee('"uri":"file://summary/abc"');
});

it('resolves multiple URI template variables when testing resources', function (): void {
    $response = UriTemplateTestServer::resource(UriTemplateUserFileResource::class, [
        'userId' => '42',
        'fileId' => 'document.pdf',
    ]);

    $response->assertOk()
        ->assertSee('"userId":"42"')
        ->assertSee('"fileId":"document.pdf"')
        ->assertSee('"uri":"file://users/42/files/document.pdf"');
});

it('casts non-string scalar arguments when expanding URI templates', function (): void {
    $response = UriTemplateTestServer::resource(UriTemplateSummaryResource::class, ['id' => 123]);

    $response->assertOk()
        ->assertSee('"uri":"file://summary/123"');
});

it('accepts Stringable arguments when expanding URI templates', function (): void {
    $stringable = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'abc';
        }
    };

    $response = UriTemplateTestServer::resource(UriTemplateSummaryResource::class, ['id' => $stringable]);

    $response->assertOk()
        ->assertSee('"uri":"file://summary/abc"');
});

it('ignores extra arguments that do not appear in the URI template', function (): void {
    $response = UriTemplateTestServer::resource(UriTemplateSummaryResource::class, [
        'id' => 'abc',
        'extra' => 'value',
    ]);

    $response->assertOk()
        ->assertSee('"uri":"file://summary/abc"');
});

it('throws when a required URI template variable is missing', function (): void {
    UriTemplateTestServer::resource(UriTemplateUserFileResource::class, ['userId' => '42']);
})->throws(InvalidArgumentException::class, 'Missing value for URI template variable [fileId]');

it('throws when a URI template variable value contains a slash', function (): void {
    UriTemplateTestServer::resource(UriTemplateSummaryResource::class, ['id' => 'a/b']);
})->throws(InvalidArgumentException::class, "URI template variable [id] value must not contain '/'");

it('throws when a URI template variable value is not scalar or Stringable', function (): void {
    UriTemplateTestServer::resource(UriTemplateSummaryResource::class, ['id' => ['array']]);
})->throws(InvalidArgumentException::class, 'URI template variable [id] must be a scalar or Stringable value');
