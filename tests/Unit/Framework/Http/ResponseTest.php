<?php

declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Response;
use SimpleXMLElement;

/**
 * Locks in the surface added in the DRY refactor:
 *   - Response::json() sets code + Content-Type + encodes
 *   - Response::xml() mirrors it for the XML output path
 *   - Pre-encoded string bodies pass through verbatim (no double encode)
 */
final class ResponseTest extends TestCase
{
    // -- JSON -----------------------------------------------------------

    public function testJsonEncodesArrayAndSetsHeaders(): void
    {
        $r = new Response();
        $body = $r->json(['ok' => true, 'count' => 3]);

        $this->assertSame('{"ok":true,"count":3}', $body);
        $this->assertSame(200, $r->getCode());
        $this->assertSame('application/json; charset=utf-8', $r->getHeader('Content-Type'));
    }

    public function testJsonHonoursCustomStatusCode(): void
    {
        $r = new Response();
        $r->json(['error' => 'nope'], 422);

        $this->assertSame(422, $r->getCode());
    }

    public function testJsonPassesAlreadyEncodedStringThrough(): void
    {
        $r = new Response();
        $pre = '{"manually":"encoded","pretty":true}';

        // No double-encode — the literal string survives.
        $this->assertSame($pre, $r->json($pre));
    }

    public function testJsonRespectsFlagsArgument(): void
    {
        $r = new Response();
        $body = $r->json(['url' => 'https://example.com/x'], 200, JSON_UNESCAPED_SLASHES);

        // JSON_UNESCAPED_SLASHES: forward slashes stay literal.
        $this->assertStringContainsString('https://example.com/x', $body);
    }

    // -- XML ------------------------------------------------------------

    public function testXmlSerialisesArrayWithRootElement(): void
    {
        $r = new Response();
        $body = $r->xml(['ok' => 'yes', 'count' => 3], 200, 'envelope');

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $body);
        $this->assertStringContainsString('<envelope>', $body);
        $this->assertStringContainsString('<ok>yes</ok>', $body);
        $this->assertStringContainsString('<count>3</count>', $body);
        $this->assertSame('application/xml; charset=utf-8', $r->getHeader('Content-Type'));
    }

    public function testXmlHonoursCustomStatusCode(): void
    {
        $r = new Response();
        $r->xml(['error' => 'nope'], 503);

        $this->assertSame(503, $r->getCode());
    }

    public function testXmlPassesStringThroughVerbatim(): void
    {
        $r = new Response();
        $pre = '<?xml version="1.0"?><root><hand>crafted</hand></root>';

        $this->assertSame($pre, $r->xml($pre));
    }

    public function testXmlAcceptsSimpleXmlElement(): void
    {
        $r = new Response();
        $el = new SimpleXMLElement('<?xml version="1.0"?><root><a>1</a></root>');

        $body = $r->xml($el);
        $this->assertStringContainsString('<a>1</a>', $body);
        $this->assertStringContainsString('<root>', $body);
    }

    public function testXmlSerialisesNumericKeysAsIndexedItems(): void
    {
        $r = new Response();
        $body = $r->xml(['tags' => ['admin', 'editor']]);

        // Numeric keys map to <item index="N"> so the XML stays valid.
        $this->assertStringContainsString('<item index="0">admin</item>', $body);
        $this->assertStringContainsString('<item index="1">editor</item>', $body);
    }

    public function testXmlSanitisesInvalidElementNames(): void
    {
        $r = new Response();
        // Spaces / digits-at-start aren't valid XML element names; they
        // should be replaced/prefixed so the document parses.
        $body = $r->xml(['has space' => 'x', '1starts-with-digit' => 'y']);

        $this->assertTrue(@simplexml_load_string($body) !== false, 'XML must be well-formed');
    }

    public function testXmlHandlesNestedArrays(): void
    {
        $r = new Response();
        $body = $r->xml(['user' => ['id' => 42, 'profile' => ['name' => 'X']]]);
        $parsed = simplexml_load_string($body);

        $this->assertSame('42', (string) $parsed->user->id);
        $this->assertSame('X', (string) $parsed->user->profile->name);
    }
}
