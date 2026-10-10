<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarkdownPreviewControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_renders_a_markdown_preview(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/markdown/preview', [
            'content' => "Hello **world**\n\n<script>alert(1)</script>",
        ]);

        $response->assertOk();
        $html = $response->json('data.html');
        $this->assertStringContainsString('<p>Hello <strong>world</strong></p>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('/markdown/preview', ['content' => 'hello'])->assertUnauthorized();
    }
}
