<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_displays_public_marketing_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Döngü ile ambalajlar')
            ->assertSee('Döngü Uygulaması nedir?')
            ->assertSee('Döngü cebinde, ambalajların değeri yanında.')
            ->assertSee('https://schema.org', false)
            ->assertDontSee('&lt;?php');
    }
}
