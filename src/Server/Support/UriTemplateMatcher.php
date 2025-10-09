<?php

declare(strict_types=1);

namespace Laravel\Mcp\Server\Support;

class UriTemplateMatcher
{
    /**
     * Check if a URI matches a URI template pattern.
     *
     * @param  string  $uriTemplate  The URI template with {variable} placeholders
     * @param  string  $uri  The actual URI to match
     */
    public static function matches(string $uriTemplate, string $uri): bool
    {
        $pattern = self::templateToRegex($uriTemplate);

        return (bool) preg_match($pattern, $uri);
    }

    /**
     * Extract variables from a URI based on a URI template.
     *
     * @param  string  $uriTemplate  The URI template with {variable} placeholders
     * @param  string  $uri  The actual URI to extract variables from
     * @return array<string, string> Array of variable name => value pairs
     */
    public static function extract(string $uriTemplate, string $uri): array
    {
        $pattern = self::templateToRegex($uriTemplate, capture: true);

        if (in_array(preg_match($pattern, $uri, $matches), [0, false], true)) {
            return [];
        }

        // Remove numeric keys, keep only named captures
        return array_filter($matches, fn ($key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Convert a URI template to a regular expression pattern.
     *
     * Supports simple RFC 6570 templates with {variable} syntax.
     *
     * @param  string  $template  The URI template
     * @param  bool  $capture  Whether to use named capture groups
     */
    protected static function templateToRegex(string $template, bool $capture = false): string
    {
        // Escape special regex characters except {}
        $pattern = preg_quote($template, '#');

        // Replace escaped braces back to actual braces for processing
        $pattern = str_replace(['\{', '\}'], ['{', '}'], $pattern);

        // Replace {variable} with regex pattern
        if ($capture) {
            // Named capture groups for variable extraction
            $pattern = preg_replace('/\{([a-zA-Z_]\w*)\}/', '(?<$1>[^/]+)', $pattern);
        } else {
            // Non-capturing groups for matching only
            $pattern = preg_replace('/\{[a-zA-Z_]\w*\}/', '[^/]+', $pattern);
        }

        return '#^'.$pattern.'$#';
    }
}
