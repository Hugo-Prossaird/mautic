<?php

namespace Mautic\PageBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\PageBundle\Entity\Page;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class PublicControllerFunctionalTest extends MauticMysqlTestCase
{
    /**
     * @dataProvider xssPayloadsProvider
     */
    public function testContactTrackingTagsXss(string $payload, ?string $expectedSanitized): void
    {
        $page = new Page();
        $page->setIsPublished(true);
        $page->setTitle('XSS Test');
        $page->setAlias('xss-test');
        $page->setCustomHtml('xss-test');
        $this->em->persist($page);
        $this->em->flush();

        $encodedPayload = urlencode($payload);
        $this->client->request(Request::METHOD_GET, "/xss-test?tags={$encodedPayload}");
        Assert::assertTrue($this->client->getResponse()->isOk());

        $tagRepository = $this->em->getRepository(Tag::class);
        $tags          = $tagRepository->findAll();

        if ($expectedSanitized) {
            // Assert that a tag was created
            Assert::assertCount(1, $tags);

            // Get the created tag
            $tag = $tags[0];

            // Assert that the tag name does not contain the malicious script
            Assert::assertStringNotContainsString('<script>', $tag->getTag());
            Assert::assertStringNotContainsString('</script>', $tag->getTag());

            // Assert that the tag name has been properly sanitized
            Assert::assertEquals($expectedSanitized, $tag->getTag());
        } else {
            // Assert that a tag was NOT created
            Assert::assertCount(0, $tags);
        }

        // Check the response content to ensure no script is present
        $content = $this->client->getResponse()->getContent();
        Assert::assertStringNotContainsString($payload, $content);
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    public static function xssPayloadsProvider(): array
    {
        return [
            'Basic script tag' => [
                '<script>alert(1)</script>',
                'alert(1)',
            ],
            'Script tag with attributes' => [
                '<script src="http://example.com/evil.js"></script>',
                null,
            ],
            'Encoded script tag' => [
                '&#60;script&#62;alert(1)&#60;/script&#62;',
                'alert(1)',
            ],
            'On-event handler' => [
                '<img src="x" onerror="alert(1)">',
                null,
            ],
            'JavaScript protocol in URL' => [
                '<a href="javascript:alert(1)">Click me</a>',
                'Click me',
            ],
            'SVG with embedded script' => [
                '<svg><script>alert(1)</script></svg>',
                'alert(1)',
            ],
            'CSS expression' => [
                '<div style="background:url(javascript:alert(1))">',
                null,
            ],
            'Malformed tag' => [
                '<img """><script>alert("XSS")</script>"<',
                'alert("XSS")"',
            ],
            'Malformed tag2' => [
                '<IMG SRC="jav&#x09;ascript:alert(\'XSS\');">',
                null,
            ],
            'Unicode escape' => [
                '<script>\u0061lert(1)</script>',
                '\u0061lert(1)',
            ],
        ];
    }
}