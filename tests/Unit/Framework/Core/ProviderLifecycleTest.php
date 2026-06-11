<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Silver\Core\Bootstrap\AbstractProvider;
use Silver\Core\Container;
use Silver\Core\Env;
use Silver\Core\Kernel;
use Silver\Http\Request;
use Silver\Http\Response;

final class RecordingProviderA extends AbstractProvider
{
    /** @var list<string> */
    public static array $calls = [];

    public function __construct(public mixed $kernel = null)
    {
    }

    public function register(Container $container): void
    {
        self::$calls[] = 'A.register';
    }

    public function before(Request $request, Response $response): void
    {
        self::$calls[] = 'A.before';
    }

    public function after(Request $request, Response $response): void
    {
        self::$calls[] = 'A.after';
    }
}

final class RecordingProviderB extends AbstractProvider
{
    public function __construct(public mixed $kernel = null)
    {
    }

    public function register(Container $container): void
    {
        RecordingProviderA::$calls[] = 'B.register';
        $container->instance('provider-binding', new \stdClass());
    }

    public function before(Request $request, Response $response): void
    {
        RecordingProviderA::$calls[] = 'B.before';
        // Phase contract: bindings from EVERY provider's register() are
        // visible by the time any before() runs.
        RecordingProviderA::$calls[] = app()->make('provider-binding') instanceof \stdClass
            ? 'B.sees-binding'
            : 'B.missing-binding';
    }
}

final class ProviderLifecycleTest extends TestCase
{
    private mixed $savedEnvData;

    protected function setUp(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $this->savedEnvData = $prop->getValue();
        RecordingProviderA::$calls = [];
    }

    protected function tearDown(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $prop->setValue(null, $this->savedEnvData);
    }

    public function testRegisterRunsForAllProvidersBeforeAnyBefore(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $prop->setValue(null, json_decode(json_encode([
            'providers' => [RecordingProviderA::class, RecordingProviderB::class],
        ])));

        $kernel = new Kernel();
        $kernel->loadProviders(new Request(), new Response());

        $this->assertSame(
            ['A.register', 'B.register', 'A.before', 'B.before', 'B.sees-binding'],
            RecordingProviderA::$calls,
        );

        $kernel->finalizeProviders(new Request(), new Response());
        $this->assertContains('A.after', RecordingProviderA::$calls);
    }
}
