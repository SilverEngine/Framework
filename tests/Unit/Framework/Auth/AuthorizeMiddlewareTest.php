<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Access\Gate;
use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Middleware\Authorize;
use Silver\Core\Route;
use Silver\Http\AuthorizationException;
use Silver\Http\Request;
use Silver\Http\Response;

final class AuthorizeMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = $_POST = $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/dash';
        app(Route::class)->reset();
    }

    private function user(string $role): Authenticatable
    {
        return new GateUser(role: $role);
    }

    public function testAllowsWhenGatePasses(): void
    {
        $gate = new Gate(fn (): ?Authenticatable => $this->user('admin'));
        $gate->define('view-dash', fn (?Authenticatable $u): bool => $u instanceof GateUser && $u->role === 'admin');

        $mw  = new Authorize($gate, 'view-dash');
        $out = $mw->execute(new Request(), new Response(), fn (): string => 'ok');

        self::assertSame('ok', $out);
    }

    public function testThrows403WhenGateDenies(): void
    {
        $gate = new Gate(fn (): ?Authenticatable => $this->user('member'));
        $gate->define('view-dash', fn (?Authenticatable $u): bool => $u instanceof GateUser && $u->role === 'admin');

        $mw = new Authorize($gate, 'view-dash');

        $this->expectException(AuthorizationException::class);
        $mw->execute(new Request(), new Response(), fn (): string => 'ok');
    }

    public function testRouteParamIsForwardedToTheGate(): void
    {
        $_SERVER['REQUEST_URI'] = '/posts/41';
        app(Route::class)->get('/posts/{post}', 'Posts@show');

        $gate = new Gate(fn (): ?Authenticatable => $this->user('member'));
        $gate->define(
            'edit-post',
            fn (?Authenticatable $u, mixed $postId): bool => (string) $postId === '41',
        );

        $mw  = new Authorize($gate, 'edit-post', 'post');
        $out = $mw->execute(new Request(), new Response(), fn (): string => 'ok');

        self::assertSame('ok', $out);

        $_SERVER['REQUEST_URI'] = '/posts/99';
        $this->expectException(AuthorizationException::class);
        $mw->execute(new Request(), new Response(), fn (): string => 'ok');
    }
}
