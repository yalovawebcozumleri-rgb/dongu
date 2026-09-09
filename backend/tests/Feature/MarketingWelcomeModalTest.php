<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingWelcomeModalTest extends TestCase
{
    public function test_marketing_visit_contains_session_based_download_welcome_modal(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-welcome-modal', false)
            ->assertSee('data-session-key="dongu_welcome_seen_v3"', false)
            ->assertSee('Ambalajların değerini')
            ->assertSee('welcome-modal-download', false)
            ->assertSee('Döngü’yü hemen indir')
            ->assertSee('iPhone ve Android için')
            ->assertDontSee('welcome-modal-orbit-logo', false)
            ->assertDontSee('welcome-phone', false)
            ->assertSee('Döngü Uygulamasını İndir')
            ->assertSee(route('listings.index'), false)
            ->assertSee('site/welcome-modal.css', false)
            ->assertSee('site/welcome-modal.js', false)
            ->assertSee('site/open-dongu-app.js', false);
    }

    public function test_legal_page_does_not_interrupt_the_user_with_welcome_modal(): void
    {
        $this->get('/gizlilik-politikasi')
            ->assertOk()
            ->assertDontSee('data-welcome-modal', false);
    }
}
