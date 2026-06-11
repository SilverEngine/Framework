<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use Closure;
use PHPUnit\Framework\TestCase;
use Silver\Core\Container;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\MiddlewareResolver;
use Silver\Http\Request;
use Silver\Http\Response;

final class MrPlain implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        return $next();
    }
}

final class MrThrottleLike implements MiddlewareInterface
{
    public function __construct(
        public readonly int $maxAttempts = 0,
        public readonly int $decaySeconds = 0,
    ) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        return $next();
    }
}

final class MrNeedsService implements MiddlewareInterface
{
    public function __construct(
        public readonly Container $container,
        public readonly int $limit = 10,
    ) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        return $next();
    }
}

final class MiddlewareResolverTest extends TestCase
{
    private function resolver(array $aliases = [], array $groups = []): MiddlewareResolver
    {
        return new MiddlewareResolver($aliases, $groups, new Container());
    }

    public function testAliasResolvesToInstance(): void
    {
        $resolver = $this->resolver(['plain' => MrPlain::class]);

        $mws = $resolver->resolve(['plain']);

        self::assertCount(1, $mws);
        self::assertInstanceOf(MrPlain::class, $mws[0]);
    }

    public function testBareClassNameResolvesDirectly(): void
    {
        $mws = $this->resolver()->resolve([MrPlain::class]);

        self::assertInstanceOf(MrPlain::class, $mws[0]);
    }

    public function testParamsParsedAndCastToConstructorTypes(): void
    {
        $resolver = $this->resolver(['throttle' => MrThrottleLike::class]);

        $mws = $resolver->resolve(['throttle:60,1']);

        self::assertInstanceOf(MrThrottleLike::class, $mws[0]);
        self::assertSame(60, $mws[0]->maxAttempts);
        self::assertSame(1, $mws[0]->decaySeconds);
    }

    public function testServiceDepsAutowiredAlongsideParams(): void
    {
        $resolver = $this->resolver(['svc' => MrNeedsService::class]);

        $mws = $resolver->resolve(['svc:25']);

        self::assertInstanceOf(MrNeedsService::class, $mws[0]);
        self::assertInstanceOf(Container::class, $mws[0]->container);
        self::assertSame(25, $mws[0]->limit);
    }

    public function testGroupExpandsToItsMembers(): void
    {
        $resolver = $this->resolver(
            ['plain' => MrPlain::class, 'throttle' => MrThrottleLike::class],
            ['api' => ['plain', 'throttle:30,2']],
        );

        $mws = $resolver->resolve(['api']);

        self::assertCount(2, $mws);
        self::assertInstanceOf(MrPlain::class, $mws[0]);
        self::assertInstanceOf(MrThrottleLike::class, $mws[1]);
        self::assertSame(30, $mws[1]->maxAttempts);
    }

    public function testNestedGroupsExpandRecursively(): void
    {
        $resolver = $this->resolver(
            ['plain' => MrPlain::class],
            ['base' => ['plain'], 'web' => ['base', MrThrottleLike::class]],
        );

        $mws = $resolver->resolve(['web']);

        self::assertCount(2, $mws);
        self::assertInstanceOf(MrPlain::class, $mws[0]);
        self::assertInstanceOf(MrThrottleLike::class, $mws[1]);
    }

    public function testUnknownNameThrows(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessageMatches('/nope/');

        $this->resolver()->resolve(['nope']);
    }

    public function testResolvedMiddlewareMustImplementInterface(): void
    {
        $this->expectException(\Throwable::class);

        $this->resolver(['bad' => \stdClass::class])->resolve(['bad']);
    }
}
