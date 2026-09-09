<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingPartnershipPageTest extends TestCase
{
    public function test_partnership_page_is_publicly_available(): void
    {
        $this->get('/reklam-ve-isbirligi')
            ->assertOk()
            ->assertSee('Reklam ve kurumsal iş birliği')
            ->assertSee('E-posta gönder')
            ->assertSee('WhatsApp’tan yaz');

        $css = file_get_contents(public_path('site/marketing-vision.css'));
        $this->assertStringContainsString('.vision-partnership-benefits h3{font-family:Manrope,sans-serif!important;font-weight:900!important', $css);
    }

    public function test_marketing_footer_links_to_partnership_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('marketing.partnerships'), false)
            ->assertSee('Reklam ve İş Birliği');
    }

    public function test_sitemap_contains_partnership_page_but_not_download_redirect(): void
    {
        $sitemap = file_get_contents(public_path('sitemap.xml'));

        $this->assertStringContainsString('/reklam-ve-isbirligi</loc>', $sitemap);
        $this->assertStringContainsString('/ilanlar</loc>', $sitemap);
        $this->assertStringNotContainsString('/indir</loc>', $sitemap);
    }
}
