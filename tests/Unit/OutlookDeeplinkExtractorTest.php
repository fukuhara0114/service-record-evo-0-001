<?php

namespace Tests\Unit;

use App\Services\Gmail\OutlookDeeplinkExtractor;
use PHPUnit\Framework\TestCase;

class OutlookDeeplinkExtractorTest extends TestCase
{
    public function test_extracts_outlook_links_from_html_and_text(): void
    {
        $extractor = new OutlookDeeplinkExtractor;
        $hosts = ['outlook.office.com', 'outlook.office365.com'];

        $html = '<p><a href="https://outlook.office.com/mail/deeplink/read?ItemID=abc">open</a></p>';
        $text = "see also https://outlook.office365.com/owa/?ItemID=xyz\nand https://example.com/nope";

        $urls = $extractor->extract($html, $text, $hosts);

        $this->assertCount(2, $urls);
        $this->assertSame('https://outlook.office.com/mail/deeplink/read?ItemID=abc', $urls[0]);
        $this->assertSame('https://outlook.office365.com/owa/?ItemID=xyz', $urls[1]);
    }

    public function test_ignores_non_outlook_urls(): void
    {
        $extractor = new OutlookDeeplinkExtractor;
        $urls = $extractor->extract(
            '<a href="https://mail.google.com/mail/u/0/#inbox/x">g</a>',
            'https://example.com/path',
            ['outlook.office.com']
        );

        $this->assertSame([], $urls);
    }
}
