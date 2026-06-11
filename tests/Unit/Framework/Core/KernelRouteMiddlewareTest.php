<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use Closure;
use PHPUnit\Framework\TestCase;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Kernel;
use Silver\Http\Request;
use Silver\Http\Response;

final class KrmRecorder implements MiddlewareInterface
{
    /** @var list<string> */
    public static array $log = [];

    public function __construct(private readonly string $tag = '?') {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        self::$log[] = 'before:' . $this->tag;
        $out = $next();
        self::$log[] = 'after:' . $this->tag;
        return $out;
    }
}

final class KrmShortCircuit implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        return 'blocked';
    }
}

/** Exposes the protected chain runner for direct testing. */
final class KrmKernel extends Kernel
{
    public function chain(array $mws, Request $req, Response $res, Closure $destination): mixed
    {
        return $this->executeMiddlewares($mws, $req, $res, $destination);
    }
}

final class KernelRouteMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        KrmRecorder::$log = [];
    }

    public function testChainRunsInOrderAroundDestination(): void
    {
        $kernel = new KrmKernel();
        $out = $kernel->chain(
            [new KrmRecorder('a'), new KrmRecorder('b')],
            new Request(),
            new Response(),
            function (): string {
                KrmRecorder::$log[] = 'destination';
                return 'result';
            },
        );

        self::assertSame('result', $out);
        self::assertSame(
            ['before:a', 'before:b', 'destination', 'after:b', 'after:a'],
            KrmRecorder::$log,
        );
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $kernel = new KrmKernel();
        $out = $kernel->chain(
            [new KrmRecorder('a'), new KrmShortCircuit(), new KrmRecorder('never')],
            new Request(),
            new Response(),
            function (): string {
                KrmRecorder::$log[] = 'destination';
                return 'result';
            },
        );

        self::assertSame('blocked', $out);
        self::assertSame(['before:a', 'after:a'], KrmRecorder::$log);
    }
}
