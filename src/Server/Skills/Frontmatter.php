<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Skills;

use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Frontmatter
{
    public const NAME_PATTERN = '/\A[\p{Ll}\p{Lo}\p{N}]+(?:-[\p{Ll}\p{Lo}\p{N}]+)*\z/u';

    /**
     * @return array<string, mixed>&array{name: string, description: string}
     */
    public static function parse(string $contents): array
    {
        if (! mb_check_encoding($contents, 'UTF-8') || preg_match('/\A---\r?\n(.*?)^---[ \t]*(?:\r?\n|\z)/ms', $contents, $matches) !== 1) {
            throw new RuntimeException('SKILL.md must contain UTF-8 text and begin with YAML frontmatter.');
        }

        try {
            $frontmatter = Yaml::parse($matches[1], Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_DATETIME);
        } catch (ParseException $parseException) {
            throw new RuntimeException("The SKILL.md frontmatter is not valid YAML: {$parseException->getMessage()}", $parseException->getCode(), previous: $parseException);
        }

        if (! $frontmatter instanceof stdClass) {
            throw new RuntimeException('The SKILL.md frontmatter must be a YAML mapping.');
        }

        static::ensureJsonCompatible($frontmatter);

        $frontmatter = get_object_vars($frontmatter);

        static::validate($frontmatter);

        /** @var array<string, mixed>&array{name: string, description: string} $frontmatter */
        return $frontmatter;
    }

    /**
     * @param  array<string, mixed>  $frontmatter
     */
    public static function render(array $frontmatter, string $body): string
    {
        $yaml = Yaml::dump($frontmatter, 8, 2, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        return "---\n{$yaml}---\n\n".ltrim($body, "\r\n");
    }

    /**
     * @param  array<array-key, mixed>  $frontmatter
     */
    protected static function validate(array $frontmatter): void
    {
        if (! is_string($frontmatter['name'] ?? null) || ! is_string($frontmatter['description'] ?? null)) {
            throw new RuntimeException('The SKILL.md frontmatter must contain a [name] and a [description].');
        }

        if (mb_strlen($frontmatter['name']) > 64 || preg_match(self::NAME_PATTERN, $frontmatter['name']) !== 1) {
            throw new RuntimeException("The skill name [{$frontmatter['name']}] must be at most 64 lowercase letters, numbers, and single hyphens.");
        }

        if (trim($frontmatter['description']) === '' || mb_strlen($frontmatter['description']) > 1024) {
            throw new RuntimeException('The skill description must contain between 1 and 1024 characters.');
        }

        foreach (['license', 'compatibility', 'allowed-tools'] as $field) {
            if (array_key_exists($field, $frontmatter) && ! is_string($frontmatter[$field])) {
                throw new RuntimeException("The skill [{$field}] field must be a string.");
            }
        }

        if (isset($frontmatter['compatibility']) && (trim($frontmatter['compatibility']) === '' || mb_strlen($frontmatter['compatibility']) > 500)) {
            throw new RuntimeException('The skill [compatibility] field must contain between 1 and 500 characters.');
        }

        if (array_key_exists('metadata', $frontmatter)) {
            $metadata = $frontmatter['metadata'];

            if (! $metadata instanceof stdClass || array_filter(get_object_vars($metadata), fn (mixed $value): bool => ! is_string($value)) !== []) {
                throw new RuntimeException('The skill [metadata] field must be a mapping of strings to strings.');
            }
        }
    }

    protected static function ensureJsonCompatible(mixed $value): void
    {
        if (is_object($value) && ! $value instanceof stdClass) {
            throw new RuntimeException('The SKILL.md frontmatter must use JSON compatible values. Quote dates and timestamps to keep them as strings.');
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new RuntimeException('The SKILL.md frontmatter must use JSON compatible values. Infinite and NaN numbers are not supported.');
        }

        if (is_array($value) || $value instanceof stdClass) {
            foreach ((array) $value as $child) {
                static::ensureJsonCompatible($child);
            }
        }
    }
}
