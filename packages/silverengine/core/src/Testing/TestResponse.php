<?php
declare(strict_types=1);

namespace Silver\Testing;

use PHPUnit\Framework\Assert;
use Silver\Http\Response;

/**
 * The result of a {@see TestCase} HTTP call: the Response object plus
 * the body exactly as {@see Response::send()} would emit it. Every
 * assert returns $this for chaining.
 */
final class TestResponse
{
    public function __construct(
        private readonly Response $response,
        private readonly string $body,
    ) {}

    // ---------- accessors ----------

    public function status(): int
    {
        return $this->response->getCode();
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->response->getHeader($key, $default);
    }

    /**
     * The decoded JSON body, or one value out of it by dot path
     * (`json('user.roles.0')`).
     */
    public function json(?string $path = null): mixed
    {
        $decoded = json_decode($this->body, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($path === null) {
            return $decoded;
        }

        $node = $decoded;
        foreach (explode('.', $path) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return null;
            }
            $node = $node[$part];
        }
        return $node;
    }

    // ---------- asserts ----------

    public function assertStatus(int $expected): self
    {
        Assert::assertSame(
            $expected,
            $this->status(),
            "Expected response status {$expected}, got {$this->status()}.",
        );
        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertSee(string $text): self
    {
        Assert::assertStringContainsString($text, $this->body, 'Response body does not contain expected text.');
        return $this;
    }

    public function assertDontSee(string $text): self
    {
        Assert::assertStringNotContainsString($text, $this->body, 'Response body contains unexpected text.');
        return $this;
    }

    /**
     * Assert the JSON body contains $subset — extra keys in the actual
     * document are fine, listed keys must match (recursively).
     *
     * @param array<array-key, mixed> $subset
     */
    public function assertJson(array $subset): self
    {
        $this->assertSubset($subset, $this->json(), '$');
        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): self
    {
        Assert::assertSame(
            $expected,
            $this->json($path),
            "JSON path [{$path}] does not match.",
        );
        return $this;
    }

    /** Assert a 3xx status; with $to, that Location matches exactly. */
    public function assertRedirect(?string $to = null): self
    {
        $status = $this->status();
        Assert::assertTrue(
            $status >= 300 && $status < 400,
            "Expected a redirect status, got {$status}.",
        );

        if ($to !== null) {
            Assert::assertSame($to, $this->header('Location'), 'Redirect Location does not match.');
        }
        return $this;
    }

    public function assertHeader(string $key, ?string $value = null): self
    {
        $actual = $this->header($key);
        Assert::assertNotNull($actual, "Response is missing header [{$key}].");

        if ($value !== null) {
            Assert::assertSame($value, (string) $actual, "Header [{$key}] does not match.");
        }
        return $this;
    }

    /** @param array<array-key, mixed> $subset */
    private function assertSubset(array $subset, mixed $actual, string $at): void
    {
        Assert::assertIsArray($actual, "JSON at {$at} is not an array/object.");

        foreach ($subset as $key => $value) {
            Assert::assertArrayHasKey($key, $actual, "JSON at {$at} is missing key [{$key}].");

            if (is_array($value)) {
                $this->assertSubset($value, $actual[$key], "{$at}.{$key}");
            } else {
                Assert::assertSame($value, $actual[$key], "JSON value at {$at}.{$key} does not match.");
            }
        }
    }
}
