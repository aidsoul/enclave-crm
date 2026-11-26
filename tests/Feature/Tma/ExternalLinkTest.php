<?php

namespace Tests\Feature\Tma;

use Tests\TestCase;

class ExternalLinkTest extends TestCase
{
    public function test_viber_redirect_page_renders_with_sanitized_phone(): void
    {
        $response = $this->get('/open/viber?phone=+380 (99) 123-45-67');

        $response->assertStatus(200);
        $response->assertSee('viber://chat?number=%2B380991234567', false);
        $response->assertSee('+380991234567');
        $response->assertSee('Открыть Viber');
    }

    public function test_viber_redirect_page_handles_phone_without_plus(): void
    {
        $response = $this->get('/open/viber?phone=380991234567');

        $response->assertStatus(200);
        $response->assertSee('viber://chat?number=%2B380991234567', false);
    }

    public function test_email_redirect_page_renders_with_mailto_and_gmail(): void
    {
        $response = $this->get('/open/email?to=client@example.com');

        $response->assertStatus(200);
        $response->assertSee('mailto:client@example.com', false);
        $response->assertSee('https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=client%40example.com', false);
        $response->assertSee('client@example.com');
        $response->assertSee('Почтовый клиент');
        $response->assertSee('Открыть в Gmail');
    }

    public function test_telegram_redirect_page_with_username(): void
    {
        $response = $this->get('/open/telegram?username=@test_user');

        $response->assertStatus(200);
        $response->assertSee('https://t.me/test_user', false);
        $response->assertSee('@test_user');
        $response->assertSee('Открыть Telegram');
    }

    public function test_telegram_redirect_page_with_id(): void
    {
        $response = $this->get('/open/telegram?id=123456789');

        $response->assertStatus(200);
        $response->assertSee('tg://user?id=123456789', false);
        $response->assertSee('ID: 123456789');
        $response->assertSee('Открыть Telegram');
    }
}
