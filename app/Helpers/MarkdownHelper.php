<?php

namespace App\Helpers;

use Illuminate\Support\Str;

use function Safe\preg_replace;

class MarkdownHelper
{
    /**
     * Render user-written Markdown as safe HTML.
     * Raw HTML is stripped and unsafe links are dropped, so the result can be
     * displayed with v-html.
     */
    public static function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return (string) Str::of($markdown)->markdown([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);
    }

    /**
     * Get the plain text version of a Markdown string, without any formatting.
     */
    public static function toPlainText(?string $markdown): string
    {
        $text = html_entity_decode(strip_tags(self::toHtml($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Get a short excerpt of the plain text, or null when the text is short enough
     * to be displayed in full.
     */
    public static function excerpt(?string $markdown, int $limit = 200): ?string
    {
        $plain = self::toPlainText($markdown);

        return Str::length($plain) > $limit ? Str::limit($plain, $limit) : null;
    }
}
