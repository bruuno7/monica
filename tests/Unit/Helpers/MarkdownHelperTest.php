<?php

namespace Tests\Unit\Helpers;

use App\Helpers\MarkdownHelper;
use Tests\TestCase;

class MarkdownHelperTest extends TestCase
{
    /** @test */
    public function it_renders_markdown_as_html(): void
    {
        $html = MarkdownHelper::toHtml("# Dinner with Ana\n\nWe talked about **the trip**.\n\n- Paris\n- Rome");

        $this->assertStringContainsString('<h1>Dinner with Ana</h1>', $html);
        $this->assertStringContainsString('<strong>the trip</strong>', $html);
        $this->assertStringContainsString('<li>Paris</li>', $html);
    }

    /** @test */
    public function it_strips_raw_html_and_unsafe_links(): void
    {
        $html = MarkdownHelper::toHtml("[click](javascript:alert(1)) <img src=x onerror=alert(1)>\n\n<script>alert(1)</script>");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('click', $html);
    }

    /** @test */
    public function it_returns_an_empty_string_for_empty_content(): void
    {
        $this->assertSame('', MarkdownHelper::toHtml(null));
        $this->assertSame('', MarkdownHelper::toHtml('   '));
    }

    /** @test */
    public function it_converts_markdown_to_plain_text(): void
    {
        $this->assertSame(
            'Dinner with Ana We talked about the trip.',
            MarkdownHelper::toPlainText("# Dinner with Ana\n\nWe talked about **the trip**.")
        );
    }

    /** @test */
    public function it_builds_an_excerpt_only_for_long_content(): void
    {
        $this->assertNull(MarkdownHelper::excerpt('**short** note'));

        $excerpt = MarkdownHelper::excerpt(str_repeat('word ', 100));
        $this->assertNotNull($excerpt);
        $this->assertStringEndsWith('...', $excerpt);
        $this->assertLessThanOrEqual(203, mb_strlen($excerpt));
    }
}
