<?php

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreventSearchIndexingTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_disallows_all_crawlers(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('User-agent: *', $response->getContent());
        $this->assertStringContainsString('Disallow: /', $response->getContent());
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag', ''));
    }

    public function test_public_pages_include_x_robots_tag_and_meta_robots(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $this->assertEquals('noindex, nofollow, noarchive, nosnippet', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet"', $response->getContent());
    }

    public function test_external_redirect_page_includes_meta_robots(): void
    {
        $response = $this->get('/open/telegram?id=12345&username=testuser');

        $response->assertStatus(200);
        $this->assertEquals('noindex, nofollow, noarchive, nosnippet', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet"', $response->getContent());
    }

    public function test_blocked_page_includes_meta_robots(): void
    {
        $rendered = view('tma.blocked')->render();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet"/>', $rendered);
    }

    public function test_desktop_auth_views_include_meta_robots(): void
    {
        $renderedError = view('tma.auth.desktop-error', ['message' => 'Test error'])->render();
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">', $renderedError);
    }

    public function test_error_pages_include_x_robots_tag_and_meta_robots(): void
    {
        $response = $this->get('/non-existent-random-page-12345');

        $response->assertStatus(404);
        $this->assertEquals('noindex, nofollow, noarchive, nosnippet', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet"', $response->getContent());
    }

    public function test_api_responses_include_x_robots_tag(): void
    {
        $response = $this->getJson('/up');

        $response->assertStatus(200);
        $this->assertEquals('noindex, nofollow, noarchive, nosnippet', $response->headers->get('X-Robots-Tag'));
    }
}
