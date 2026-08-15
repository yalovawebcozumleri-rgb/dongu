<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_documents_are_public_versioned_and_available_on_api_and_web(): void
    {
        $this->getJson('/api/v1/legal-documents/terms')
            ->assertOk()
            ->assertJsonPath('data.key', 'terms')
            ->assertJsonPath('data.version', '2026-08-05.2')
            ->assertJsonCount(15, 'data.sections')
            ->assertJsonFragment(['title' => '2. Platformun amacı, bağımsızlığı ve rolü'])
            ->assertJsonFragment(['short_title' => 'Kullanım Şartları']);

        $this->getJson('/api/v1/legal-documents/privacy')
            ->assertOk()
            ->assertJsonPath('data.key', 'privacy')
            ->assertJsonPath('data.version', '2026-08-05.2')
            ->assertJsonFragment(['title' => 'KVKK Aydınlatma Metni ve Gizlilik Politikası'])
            ->assertJsonPath('data.operator.name', 'Mustafa Polat (Yalova Web Çözümleri)')
            ->assertJsonPath('data.operator.address', 'Karpuzdere Mahallesi, Kemer 2 Sokak, Çınarcık/Yalova')
            ->assertJsonPath('data.operator.phone', '+90 541 334 22 19')
            ->assertJsonCount(12, 'data.sections');

        $this->get('/legal/terms')->assertOk()->assertSee('Kullanıcı Şartları');
        $this->get('/legal/privacy')->assertOk()->assertSee('Gizlilik Politikası');
        $this->get('/kullanim-sartlari')->assertOk()->assertSee('Kullanıcı Şartları');
        $this->get('/gizlilik-politikasi')->assertOk()->assertSee('Gizlilik Politikası');
        $this->getJson('/api/v1/legal-documents/unknown')->assertNotFound();
    }
}
