<?php

use Laravel\Mcp\Support\UriTemplate;

describe('UriTemplate isTemplate', function (): void {
    it('should return true for strings containing template expressions', function (): void {
        expect(UriTemplate::isTemplate('{foo}'))->toBeTrue()
            ->and(UriTemplate::isTemplate('/users/{id}'))->toBeTrue()
            ->and(UriTemplate::isTemplate('http://example.com/{path}/{file}'))->toBeTrue()
            ->and(UriTemplate::isTemplate('/search{?q,limit}'))->toBeTrue();
    });

    it('should return false for strings without template expressions', function (): void {
        expect(UriTemplate::isTemplate(''))->toBeFalse()
            ->and(UriTemplate::isTemplate('plain string'))->toBeFalse()
            ->and(UriTemplate::isTemplate('http://example.com/foo/bar'))->toBeFalse()
            ->and(UriTemplate::isTemplate('{}'))->toBeFalse()
            ->and(UriTemplate::isTemplate('{ }'))->toBeFalse();
    });
});

describe('UriTemplate simple string expansion', function (): void {
    it('should expand simple string variables', function (): void {
        $template = new UriTemplate('http://example.com/users/{username}');
        expect($template->expand(['username' => 'fred']))->toBe('http://example.com/users/fred')
            ->and($template->getVariableNames())->toBe(['username']);
    });

    it('should handle multiple variables', function (): void {
        $template = new UriTemplate('{x,y}');
        expect($template->expand(['x' => '1024', 'y' => '768']))->toBe('1024,768')
            ->and($template->getVariableNames())->toBe(['x', 'y']);
    });

    it('should encode reserved characters', function (): void {
        $template = new UriTemplate('{var}');
        expect($template->expand(['var' => 'value with spaces']))->toBe('value%20with%20spaces');
    });
});

describe('UriTemplate reserved expansion', function (): void {
    it('should not encode reserved characters with + operator', function (): void {
        $template = new UriTemplate('{+path}/here');
        expect($template->expand(['path' => '/foo/bar']))->toBe('%2Ffoo%2Fbar/here')
            ->and($template->getVariableNames())->toBe(['path']);
    });
});

describe('UriTemplate fragment expansion', function (): void {
    it('should add # prefix and not encode reserved chars', function (): void {
        $template = new UriTemplate('X{#var}');
        expect($template->expand(['var' => '/test']))->toBe('X#%2Ftest')
            ->and($template->getVariableNames())->toBe(['var']);
    });
});

describe('UriTemplate label expansion', function (): void {
    it('should add . prefix', function (): void {
        $template = new UriTemplate('X{.var}');
        expect($template->expand(['var' => 'test']))->toBe('X.test')
            ->and($template->getVariableNames())->toBe(['var']);
    });
});

describe('UriTemplate path expansion', function (): void {
    it('should add / prefix', function (): void {
        $template = new UriTemplate('X{/var}');
        expect($template->expand(['var' => 'test']))->toBe('X/test')
            ->and($template->getVariableNames())->toBe(['var']);
    });
});

describe('UriTemplate query expansion', function (): void {
    it('should add ? prefix and name=value format', function (): void {
        $template = new UriTemplate('X{?var}');
        expect($template->expand(['var' => 'test']))->toBe('X?var=test')
            ->and($template->getVariableNames())->toBe(['var']);
    });
});

describe('UriTemplate form continuation expansion', function (): void {
    it('should add & prefix and name=value format', function (): void {
        $template = new UriTemplate('X{&var}');
        expect($template->expand(['var' => 'test']))->toBe('X&var=test')
            ->and($template->getVariableNames())->toBe(['var']);
    });
});

describe('UriTemplate matching', function (): void {
    it('should match simple strings and extract variables', function (): void {
        $template = new UriTemplate('http://example.com/users/{username}');
        $match = $template->match('http://example.com/users/fred');
        expect($match)->toBe(['username' => 'fred']);
    });

    it('should match multiple variables', function (): void {
        $template = new UriTemplate('/users/{username}/posts/{postId}');
        $match = $template->match('/users/fred/posts/123');
        expect($match)->toBe(['username' => 'fred', 'postId' => '123']);
    });

    it('should return null for non-matching URIs', function (): void {
        $template = new UriTemplate('/users/{username}');
        $match = $template->match('/posts/123');
        expect($match)->toBeNull();
    });

    it('should handle exploded arrays', function (): void {
        $template = new UriTemplate('{/list*}');
        $match = $template->match('/red,green,blue');
        expect($match)->toBe(['list' => ['red', 'green', 'blue']]);
    });
});

describe('UriTemplate edge cases', function (): void {
    it('should handle empty variables', function (): void {
        $template = new UriTemplate('{empty}');
        expect($template->expand([]))->toBe('')
            ->and($template->expand(['empty' => '']))->toBe('');
    });

    it('should handle undefined variables', function (): void {
        $template = new UriTemplate('{a}{b}{c}');
        expect($template->expand(['b' => '2']))->toBe('2');
    });

    it('should handle special characters in variable names', function (): void {
        $template = new UriTemplate('{$var_name}');
        expect($template->expand(['$var_name' => 'value']))->toBe('value');
    });
});

describe('UriTemplate complex patterns', function (): void {
    it('should handle nested path segments', function (): void {
        $template = new UriTemplate('/api/{version}/{resource}/{id}');
        expect($template->expand([
            'version' => 'v1',
            'resource' => 'users',
            'id' => '123',
        ]))->toBe('/api/v1/users/123')
            ->and($template->getVariableNames())->toBe(['version', 'resource', 'id']);
    });

    it('should handle query parameters with arrays', function (): void {
        $template = new UriTemplate('/search{?tags*}');
        expect($template->expand([
            'tags' => ['nodejs', 'typescript', 'testing'],
        ]))->toBe('/search?tags=nodejs,typescript,testing')
            ->and($template->getVariableNames())->toBe(['tags']);
    });

    it('should handle multiple query parameters', function (): void {
        $template = new UriTemplate('/search{?q,page,limit}');
        expect($template->expand([
            'q' => 'test',
            'page' => '1',
            'limit' => '10',
        ]))->toBe('/search?q=test&page=1&limit=10')
            ->and($template->getVariableNames())->toBe(['q', 'page', 'limit']);
    });
});

describe('UriTemplate matching complex patterns', function (): void {
    it('should match nested path segments', function (): void {
        $template = new UriTemplate('/api/{version}/{resource}/{id}');
        $match = $template->match('/api/v1/users/123');
        expect($match)->toBe([
            'version' => 'v1',
            'resource' => 'users',
            'id' => '123',
        ])
            ->and($template->getVariableNames())->toBe(['version', 'resource', 'id']);
    });

    it('should match query parameters', function (): void {
        $template = new UriTemplate('/search{?q}');
        $match = $template->match('/search?q=test');
        expect($match)->toBe(['q' => 'test'])
            ->and($template->getVariableNames())->toBe(['q']);
    });

    it('should match multiple query parameters', function (): void {
        $template = new UriTemplate('/search{?q,page}');
        $match = $template->match('/search?q=test&page=1');
        expect($match)->toBe(['q' => 'test', 'page' => '1'])
            ->and($template->getVariableNames())->toBe(['q', 'page']);
    });

    it('should handle partial matches correctly', function (): void {
        $template = new UriTemplate('/users/{id}');
        expect($template->match('/users/123/extra'))->toBeNull()
            ->and($template->match('/users'))->toBeNull();
    });
});

describe('UriTemplate security and edge cases', function (): void {
    it('should handle extremely long input strings', function (): void {
        $longString = str_repeat('x', 100000);
        $template = new UriTemplate('/api/{param}');
        expect($template->expand(['param' => $longString]))->toBe('/api/' . $longString)
            ->and($template->match('/api/' . $longString))->toBe(['param' => $longString]);
    });

    it('should handle deeply nested template expressions', function (): void {
        $template = new UriTemplate(str_repeat('{a}{b}{c}{d}{e}{f}{g}{h}{i}{j}', 1000));
        expect(fn (): string => $template->expand([
            'a' => '1',
            'b' => '2',
            'c' => '3',
            'd' => '4',
            'e' => '5',
            'f' => '6',
            'g' => '7',
            'h' => '8',
            'i' => '9',
            'j' => '0',
        ]))->not->toThrow(Exception::class);
    });

    it('should handle malformed template expressions', function (): void {
        expect(fn(): \Laravel\Mcp\Support\UriTemplate => new UriTemplate('{unclosed'))->toThrow(InvalidArgumentException::class)
            ->and(fn(): \Laravel\Mcp\Support\UriTemplate => new UriTemplate('{}'))->not->toThrow(Exception::class)
            ->and(fn(): \Laravel\Mcp\Support\UriTemplate => new UriTemplate('{,}'))->not->toThrow(Exception::class)
            ->and(fn(): \Laravel\Mcp\Support\UriTemplate => new UriTemplate('{a}{'))->toThrow(InvalidArgumentException::class);
    });

    it('should handle pathological regex patterns', function (): void {
        $template = new UriTemplate('/api/{param}');
        $input = '/api/'.str_repeat('a', 100000);
        expect(fn (): ?array => $template->match($input))->not->toThrow(Exception::class);
    });

    it('should handle invalid UTF-8 sequences', function (): void {
        $template = new UriTemplate('/api/{param}');
        $invalidUtf8 = '���';
        expect(fn(): string => $template->expand(['param' => $invalidUtf8]))->not->toThrow(Exception::class)
            ->and(fn(): ?array => $template->match('/api/' . $invalidUtf8))->not->toThrow(Exception::class);
    });

    it('should handle template/URI length mismatches', function (): void {
        $template = new UriTemplate('/api/{param}');
        expect($template->match('/api/'))->toBeNull()
            ->and($template->match('/api'))->toBeNull()
            ->and($template->match('/api/value/extra'))->toBeNull();
    });

    it('should handle repeated operators', function (): void {
        $template = new UriTemplate('{?a}{?b}{?c}');
        expect($template->expand(['a' => '1', 'b' => '2', 'c' => '3']))->toBe('?a=1&b=2&c=3')
            ->and($template->getVariableNames())->toBe(['a', 'b', 'c']);
    });

    it('should handle overlapping variable names', function (): void {
        $template = new UriTemplate('{var}{vara}');
        expect($template->expand(['var' => '1', 'vara' => '2']))->toBe('12')
            ->and($template->getVariableNames())->toBe(['var', 'vara']);
    });

    it('should handle empty segments', function (): void {
        $template = new UriTemplate('///{a}////{b}////');
        expect($template->expand(['a' => '1', 'b' => '2']))->toBe('///1////2////')
            ->and($template->match('///1////2////'))->toBe(['a' => '1', 'b' => '2'])
            ->and($template->getVariableNames())->toBe(['a', 'b']);
    });

    it('should handle maximum template expression limit', function (): void {
        $expressions = str_repeat('{param}', 10000);
        expect(fn (): \Laravel\Mcp\Support\UriTemplate => new UriTemplate($expressions))->not->toThrow(Exception::class);
    });

    it('should handle maximum variable name length', function (): void {
        $longName = str_repeat('a', 10000);
        $template = new UriTemplate('{'.$longName.'}');
        expect(fn (): string => $template->expand([$longName => 'value']))->not->toThrow(Exception::class);
    });
});

describe('UriTemplate stringable', function (): void {
    it('should cast to string', function (): void {
        $template = new UriTemplate('/users/{id}');
        expect((string) $template)->toBe('/users/{id}');
    });
});
