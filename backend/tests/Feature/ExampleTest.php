<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_displays_public_marketing_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Elindeki ambalajı')
            ->assertSee('Döngü’yü keşfet')
            ->assertSee('Üç ambalaj türü');
    }
}
