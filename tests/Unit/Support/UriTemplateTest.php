<?php

use Laravel\Mcp\Support\UriTemplate;

describe('UriTemplate::isTemplate', function (): void {
    it('returns true for strings containing template expressions', function (): void {
        expect(UriTemplate::isTemplate('{foo}'))->toBeTrue()
            ->and(UriTemplate::isTemplate('/users/{id}'))->toBeTrue()
            ->and(UriTemplate::isTemplate('http://example.com/{path}/{file}'))->toBeTrue()
            ->and(UriTemplate::isTemplate('/search{?q,limit}'))->toBeTrue();
    });

    it('returns false for strings without template expressions', function (): void {
        expect(UriTemplate::isTemplate(''))->toBeFalse()
            ->and(UriTemplate::isTemplate('plain string'))->toBeFalse()
            ->and(UriTemplate::isTemplate('http://example.com/foo/bar'))->toBeFalse()
            ->and(UriTemplate::isTemplate('{}'))->toBeFalse()
            ->and(UriTemplate::isTemplate('{ }'))->toBeFalse();
    });
});

describe('UriTemplate simple expansion', function (): void {
    it('expands simple string variables', function (): void {
        $template = new UriTemplate('http://example.com/users/{username}');

        expect($template->expand(['username' => 'fred']))->toBe('http://example.com/users/fred')
            ->and($template->getVariableNames())->toBe(['username']);
    });

    it('handles multiple variables', function (): void {
        $template = new UriTemplate('{x,y}');

        expect($template->expand(['x' => '1024', 'y' => '768']))->toBe('1024,768')
            ->and($template->getVariableNames())->toBe(['x', 'y']);
    });

    it('encodes reserved characters', function (): void {
        $template = new UriTemplate('{var}');

        expect($template->expand(['var' => 'value with spaces']))->toBe('value%20with%20spaces');
    });
});

describe('UriTemplate reserved expansion (+)', function (): void {
    it('does not encode reserved characters', function (): void {
        $template = new UriTemplate('{+path}/here');

        expect($template->expand(['path' => '/foo/bar']))->toBe('%2Ffoo%2Fbar/here')
            ->and($template->getVariableNames())->toBe(['path']);
    });

    it('handles arrays', function (): void {
        $template = new UriTemplate('{+list}');

        expect($template->expand(['list' => ['red', 'green', 'blue']]))->toBe('red,green,blue');
    });
});

describe('UriTemplate fragment expansion (#)', function (): void {
    it('adds # prefix', function (): void {
        $template = new UriTemplate('X{#var}');

        expect($template->expand(['var' => '/test']))->toBe('X#%2Ftest')
            ->and($template->getVariableNames())->toBe(['var']);
    });

    it('handles arrays', function (): void {
        $template = new UriTemplate('X{#list}');

        expect($template->expand(['list' => ['red', 'green', 'blue']]))->toBe('X#red,green,blue');
    });
});

describe('UriTemplate label expansion (.)', function (): void {
    it('adds . prefix', function (): void {
        $template = new UriTemplate('X{.var}');

        expect($template->expand(['var' => 'test']))->toBe('X.test')
            ->and($template->getVariableNames())->toBe(['var']);
    });

    it('handles arrays', function (): void {
        $template = new UriTemplate('X{.list}');

        expect($template->expand(['list' => ['red', 'green', 'blue']]))->toBe('X.red.green.blue');
    });
});

describe('UriTemplate path expansion (/)', function (): void {
    it('adds / prefix', function (): void {
        $template = new UriTemplate('X{/var}');

        expect($template->expand(['var' => 'test']))->toBe('X/test')
            ->and($template->getVariableNames())->toBe(['var']);
    });

    it('handles arrays', function (): void {
        $template = new UriTemplate('X{/list}');

        expect($template->expand(['list' => ['red', 'green', 'blue']]))->toBe('X/red/green/blue');
    });
});

describe('UriTemplate query expansion (?)', function (): void {
    it('adds ? prefix and name=value format', function (): void {
        $template = new UriTemplate('X{?var}');

        expect($template->expand(['var' => 'test']))->toBe('X?var=test')
            ->and($template->getVariableNames())->toBe(['var']);
    });

    it('handles multiple parameters', function (): void {
        $template = new UriTemplate('/search{?q,page,limit}');

        expect($template->expand(['q' => 'test', 'page' => '1', 'limit' => '10']))
            ->toBe('/search?q=test&page=1&limit=10')
            ->and($template->getVariableNames())->toBe(['q', 'page', 'limit']);
    });

    it('handles arrays', function (): void {
        $template = new UriTemplate('/search{?tags*}');

        expect($template->expand(['tags' => ['nodejs', 'typescript', 'testing']]))
            ->toBe('/search?tags=nodejs,typescript,testing')
            ->and($template->getVariableNames())->toBe(['tags']);
    });
});

describe('UriTemplate form continuation expansion (&)', function (): void {
    it('adds & prefix and name=value format', function (): void {
        $template = new UriTemplate('X{&var}');

        expect($template->expand(['var' => 'test']))->toBe('X&var=test')
            ->and($template->getVariableNames())->toBe(['var']);
    });
});

describe('UriTemplate::match', function (): void {
    it('extracts variables from simple strings', function (): void {
        $template = new UriTemplate('http://example.com/users/{username}');

        expect($template->match('http://example.com/users/fred'))->toBe(['username' => 'fred']);
    });

    it('extracts multiple variables', function (): void {
        $template = new UriTemplate('/users/{username}/posts/{postId}');

        expect($template->match('/users/fred/posts/123'))->toBe(['username' => 'fred', 'postId' => '123']);
    });

    it('returns null for non-matching URIs', function (): void {
        $template = new UriTemplate('/users/{username}');

        expect($template->match('/posts/123'))->toBeNull();
    });

    it('handles exploded arrays', function (): void {
        $template = new UriTemplate('{/list*}');

        expect($template->match('/red,green,blue'))->toBe(['list' => ['red', 'green', 'blue']]);
    });

    it('matches nested path segments', function (): void {
        $template = new UriTemplate('/api/{version}/{resource}/{id}');

        expect($template->match('/api/v1/users/123'))->toBe([
            'version' => 'v1',
            'resource' => 'users',
            'id' => '123',
        ]);
    });

    it('matches query parameters', function (): void {
        $template = new UriTemplate('/search{?q}');

        expect($template->match('/search?q=test'))->toBe(['q' => 'test']);
    });

    it('matches multiple query parameters', function (): void {
        $template = new UriTemplate('/search{?q,page}');

        expect($template->match('/search?q=test&page=1'))->toBe(['q' => 'test', 'page' => '1']);
    });

    it('rejects partial matches', function (): void {
        $template = new UriTemplate('/users/{id}');

        expect($template->match('/users/123/extra'))->toBeNull()
            ->and($template->match('/users'))->toBeNull();
    });

    it('matches label expansion patterns', function (): void {
        $template = new UriTemplate('X{.var}');

        expect($template->match('X.test'))->toBe(['var' => 'test'])
            ->and($template->match('Xtest'))->toBeNull();
    });

    it('matches fragment expansion patterns', function (): void {
        $template = new UriTemplate('X{#var}');

        expect($template->match('X#test/value'))->toBe(['var' => '#test/value']);
    });

    it('matches reserved expansion patterns', function (): void {
        $template = new UriTemplate('{+path}');

        expect($template->match('/foo/bar'))->toBe(['path' => '/foo/bar']);
    });

    it('matches URIs with optional query parameters omitted', function (): void {
        $template = new UriTemplate('/users{?cursor}');

        expect($template->match('/users'))->toBe([])
            ->and($template->match('/users?cursor=abc123'))->toBe(['cursor' => 'abc123']);
    });

    it('matches URIs with some optional query parameters provided', function (): void {
        $template = new UriTemplate('/search{?q,page,limit}');

        expect($template->match('/search?q=test&page=1&limit=10'))->toBe([
            'q' => 'test',
            'page' => '1',
            'limit' => '10',
        ])
            ->and($template->match('/search?q=test'))->toBe(['q' => 'test'])
            ->and($template->match('/search'))->toBe([]);
    });

    it('matches query parameters in template order', function (): void {
        $template = new UriTemplate('/api{?a,b}');

        expect($template->match('/api?a=1&b=2'))->toBe(['a' => '1', 'b' => '2']);
    });
});

describe('UriTemplate edge cases', function (): void {
    it('handles empty variables', function (): void {
        $template = new UriTemplate('{empty}');

        expect($template->expand([]))->toBe('')
            ->and($template->expand(['empty' => '']))->toBe('');
    });

    it('handles undefined variables', function (): void {
        $template = new UriTemplate('{a}{b}{c}');

        expect($template->expand(['b' => '2']))->toBe('2');
    });

    it('handles special characters in variable names', function (): void {
        $template = new UriTemplate('{$var_name}');

        expect($template->expand(['$var_name' => 'value']))->toBe('value');
    });

    it('returns empty string for multiple null variables', function (): void {
        $template = new UriTemplate('{x,y,z}');

        expect($template->expand([]))->toBe('')
            ->and($template->expand(['other' => 'value']))->toBe('');
    });

    it('handles multiple variables with array values', function (): void {
        $template = new UriTemplate('{x,y}');

        expect($template->expand(['x' => ['a', 'b'], 'y' => 'c']))->toBe('a,c')
            ->and($template->expand(['x' => ['first'], 'y' => ['second']]))->toBe('first,second');
    });

    it('handles nested path segments', function (): void {
        $template = new UriTemplate('/api/{version}/{resource}/{id}');

        expect($template->expand(['version' => 'v1', 'resource' => 'users', 'id' => '123']))
            ->toBe('/api/v1/users/123')
            ->and($template->getVariableNames())->toBe(['version', 'resource', 'id']);
    });

    it('handles repeated operators', function (): void {
        $template = new UriTemplate('{?a}{?b}{?c}');

        expect($template->expand(['a' => '1', 'b' => '2', 'c' => '3']))->toBe('?a=1&b=2&c=3')
            ->and($template->getVariableNames())->toBe(['a', 'b', 'c']);
    });

    it('handles overlapping variable names', function (): void {
        $template = new UriTemplate('{var}{vara}');

        expect($template->expand(['var' => '1', 'vara' => '2']))->toBe('12')
            ->and($template->getVariableNames())->toBe(['var', 'vara']);
    });

    it('handles empty segments', function (): void {
        $template = new UriTemplate('///{a}////{b}////');

        expect($template->expand(['a' => '1', 'b' => '2']))->toBe('///1////2////')
            ->and($template->match('///1////2////'))->toBe(['a' => '1', 'b' => '2'])
            ->and($template->getVariableNames())->toBe(['a', 'b']);
    });
});

describe('UriTemplate security', function (): void {
    it('handles extremely long input strings', function (): void {
        $longString = str_repeat('x', 100000);
        $template = new UriTemplate('/api/{param}');

        expect($template->expand(['param' => $longString]))->toBe('/api/'.$longString)
            ->and($template->match('/api/'.$longString))->toBe(['param' => $longString]);
    });

    it('throws when template exceeds maximum length', function (): void {
        $longTemplate = str_repeat('x', 1000001);

        expect(fn (): UriTemplate => new UriTemplate($longTemplate))
            ->toThrow(InvalidArgumentException::class, 'Template exceeds maximum length');
    });

    it('throws when URI exceeds maximum length', function (): void {
        $template = new UriTemplate('/api/{param}');
        $longUri = '/api/'.str_repeat('x', 1000001);

        expect(fn (): ?array => $template->match($longUri))
            ->toThrow(InvalidArgumentException::class, 'URI exceeds maximum length');
    });

    it('throws when template has too many expressions', function (): void {
        $tooManyExpressions = str_repeat('{a}', 10001);

        expect(fn (): UriTemplate => new UriTemplate($tooManyExpressions))
            ->toThrow(InvalidArgumentException::class, 'Template contains too many expressions');
    });

    it('handles deeply nested template expressions', function (): void {
        $template = new UriTemplate(str_repeat('{a}{b}{c}{d}{e}{f}{g}{h}{i}{j}', 1000));

        expect(fn (): string => $template->expand([
            'a' => '1', 'b' => '2', 'c' => '3', 'd' => '4', 'e' => '5',
            'f' => '6', 'g' => '7', 'h' => '8', 'i' => '9', 'j' => '0',
        ]))->not->toThrow(Exception::class);
    });

    it('throws for unclosed template expressions', function (): void {
        expect(fn (): UriTemplate => new UriTemplate('{unclosed'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('handles empty template expressions', function (): void {
        expect(fn (): UriTemplate => new UriTemplate('{}'))->not->toThrow(Exception::class)
            ->and(fn (): UriTemplate => new UriTemplate('{,}'))->not->toThrow(Exception::class);
    });

    it('handles pathological regex patterns', function (): void {
        $template = new UriTemplate('/api/{param}');
        $input = '/api/'.str_repeat('a', 100000);

        expect(fn (): ?array => $template->match($input))->not->toThrow(Exception::class);
    });

    it('handles invalid UTF-8 sequences', function (): void {
        $template = new UriTemplate('/api/{param}');
        $invalidUtf8 = '���';

        expect(fn (): string => $template->expand(['param' => $invalidUtf8]))->not->toThrow(Exception::class)
            ->and(fn (): ?array => $template->match('/api/'.$invalidUtf8))->not->toThrow(Exception::class);
    });

    it('handles template/URI length mismatches', function (): void {
        $template = new UriTemplate('/api/{param}');

        expect($template->match('/api/'))->toBeNull()
            ->and($template->match('/api'))->toBeNull()
            ->and($template->match('/api/value/extra'))->toBeNull();
    });

    it('handles maximum template expression limit', function (): void {
        $expressions = str_repeat('{param}', 10000);

        expect(fn (): UriTemplate => new UriTemplate($expressions))->not->toThrow(Exception::class);
    });

    it('handles maximum variable name length', function (): void {
        $longName = str_repeat('a', 10000);
        $template = new UriTemplate('{'.$longName.'}');

        expect(fn (): string => $template->expand([$longName => 'value']))->not->toThrow(Exception::class);
    });
});

describe('UriTemplate::__toString', function (): void {
    it('casts to string', function (): void {
        $template = new UriTemplate('/users/{id}');

        expect((string) $template)->toBe('/users/{id}');
    });
});
