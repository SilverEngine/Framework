<?php
declare(strict_types=1);

namespace Silver\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use ReflectionProperty;
use Silver\Core\App;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Http\ArraySessionStore;
use Silver\Http\Contracts\SessionStore;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Mail\Drivers\ArrayDriver;
use Silver\Mail\Mailer;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionConfig;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Connection\TransactionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Silver\Orm\Schema\Migrator;
use Silver\Testing\Fakes\LogFake;

/**
 * Base class for application tests (G2 testing kit).
 *
 * Per test you get: a fresh app container + router, an array-backed
 * session, restored superglobals/Env afterwards, and flushed ORM
 * registries. Register routes with {@see routes()}, then drive them:
 *
 *     $this->routes(fn (Route $r) => $r->get('/x', fn () => 'hi'));
 *     $this->get('/x')->assertOk()->assertSee('hi');
 *
 * Requests run through the real Kernel pipeline — the global stack
 * from {@see middleware()} (ErrorHandler by default, so exceptions
 * become status codes), route-level middleware, DI, and the real
 * Response::send() body negotiation.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];
    /** @var array<array-key, mixed> */
    private array $getBackup = [];
    /** @var array<array-key, mixed> */
    private array $postBackup = [];
    /** @var array<array-key, mixed> */
    private array $requestBackup = [];
    /** @var array<array-key, mixed> */
    private array $sessionBackup = [];
    private mixed $envBackup = null;

    protected ?ConnectionManager $db = null;
    private ?ArrayDriver $mailDriver = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Defensive casts: a sloppy earlier test in the suite may have
        // assigned a non-array to a superglobal.
        $this->serverBackup  = is_array($_SERVER ?? null) ? $_SERVER : [];
        $this->getBackup     = is_array($_GET ?? null) ? $_GET : [];
        $this->postBackup    = is_array($_POST ?? null) ? $_POST : [];
        $this->requestBackup = is_array($_REQUEST ?? null) ? $_REQUEST : [];
        $this->sessionBackup = is_array($_SESSION ?? null) ? $_SESSION : [];
        $this->envBackup     = new ReflectionProperty(Env::class, 'envData')->getValue();

        App::refresh();
        AttributeRegistry::flush();
        ModelCache::flushAll();

        app()->instance(SessionStore::class, new ArraySessionStore());
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER  = $this->serverBackup;
        $_GET     = $this->getBackup;
        $_POST    = $this->postBackup;
        $_REQUEST = $this->requestBackup;
        $_SESSION = $this->sessionBackup;
        new ReflectionProperty(Env::class, 'envData')->setValue(null, $this->envBackup);

        App::refresh();
        AttributeRegistry::flush();
        ModelCache::flushAll();
        $this->db = null;
        $this->mailDriver = null;

        parent::tearDown();
    }

    // ---------- configuration ----------

    /** Override config for this test: `$this->config('app.debug', true)`. */
    protected function config(string $key, mixed $value): void
    {
        Env::set($key, $value);
    }

    /**
     * The global middleware stack requests run through. Override to add
     * Session/CSRF/CORS/etc.; ErrorHandler stays first so exceptions
     * become proper status codes instead of escaping the test.
     *
     * @return list<class-string>
     */
    protected function middleware(): array
    {
        return [\Silver\App\Middlewares\ErrorHandler::class];
    }

    // ---------- routing ----------

    protected function router(): Route
    {
        return app(Route::class);
    }

    /** @param Closure(Route): void $register */
    protected function routes(Closure $register): void
    {
        $register($this->router());
    }

    // ---------- HTTP ----------

    /** @param array<string, string> $headers */
    protected function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    /** @param array<string, string> $headers */
    protected function delete(string $uri, array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, [], $headers);
    }

    /**
     * Send a JSON request: $data becomes the JSON body (readable via
     * `$request->json()` and `input()`), Accept/Content-Type are set.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     */
    protected function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers += ['Accept' => 'application/json', 'Content-Type' => 'application/json'];

        return $this->call(strtoupper($method), $uri, $data, $headers, json: $data);
    }

    /**
     * @param array<string, mixed>       $data
     * @param array<string, string>      $headers
     * @param array<string, mixed>|null  $json
     */
    protected function call(string $method, string $uri, array $data = [], array $headers = [], ?array $json = null): TestResponse
    {
        // Stage the superglobals exactly as a web request would.
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        unset($_SERVER['CONTENT_TYPE']);

        $query = [];
        if (str_contains($uri, '?')) {
            parse_str(explode('?', $uri, 2)[1], $query);
        }

        $_SERVER['REQUEST_URI']    = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_ACCEPT']    = 'text/html, */*';
        $_GET     = $query;
        $_POST    = $method === 'GET' ? [] : $data;
        $_REQUEST = array_merge($query, $_POST);

        foreach ($headers as $name => $value) {
            $norm = strtoupper(str_replace('-', '_', $name));
            $_SERVER[$norm === 'CONTENT_TYPE' ? 'CONTENT_TYPE' : 'HTTP_' . $norm] = $value;
        }

        $request = new Request();
        $request->mergeInput($data);
        if ($json !== null) {
            $request->mergeJson($json);
        }
        $response = new Response();

        $container = app();
        $container->registerNamed(Request::class, $request, true);
        $container->registerNamed(Response::class, $response, true);

        $kernel = new TestKernel();
        $stack  = array_map(static fn (string $class): object => app()->make($class), $this->middleware());

        ob_start();
        try {
            $result = $kernel->dispatch($stack, $request, $response);
            if ($result !== null) {
                $response->setBody($result);
            }
            $response->send();
        } finally {
            $body = (string) ob_get_clean();
        }

        return new TestResponse($response, $body);
    }

    // ---------- fakes ----------

    /** Swap the Mailer for an in-memory driver; returns it for asserts. */
    protected function fakeMail(): ArrayDriver
    {
        $this->mailDriver = new ArrayDriver();
        app()->instance(Mailer::class, new Mailer($this->mailDriver, fromAddress: 'noreply@test.local'));

        return $this->mailDriver;
    }

    protected function assertMailSentTo(string $email): void
    {
        Assert::assertNotNull($this->mailDriver, 'Call fakeMail() before asserting on mail.');
        Assert::assertTrue(
            $this->mailDriver->hasSentTo($email),
            "No mail was sent to [{$email}].",
        );
    }

    /** Swap the Log facade target for an in-memory fake; returns it. */
    protected function fakeLog(): LogFake
    {
        $fake = new LogFake();
        app()->registerNamed(\Silver\Support\Log::class, $fake, true);

        return $fake;
    }

    // ---------- database ----------

    /**
     * Fresh in-memory sqlite, bound to the ORM and swapped into the
     * container so framework services share it.
     */
    protected function useDatabase(): ConnectionManager
    {
        $cm = new ConnectionManager();
        $cm->connect('default', 'sqlite::memory:');
        $cm->setDefault('default');

        Model::bind($cm, new EventDispatcher());
        app()->instance(ConnectionManager::class, $cm);

        return $this->db = $cm;
    }

    /**
     * In-memory sqlite (fresh, like {@see useDatabase()}) with all
     * migrations from $migrationsPath applied.
     */
    protected function migrate(string $migrationsPath): ConnectionManager
    {
        $cm = new ConnectionManager();
        $cm->registerConfig('default', new ConnectionConfig(
            driver:         Driver::Sqlite,
            dsn:            'sqlite::memory:',
            migrationsPath: $migrationsPath,
        ));
        $cm->setDefault('default');

        Model::bind($cm, new EventDispatcher());
        app()->instance(ConnectionManager::class, $cm);
        $this->db = $cm;

        new Migrator($cm, new TransactionManager($cm), 'default')->run();

        return $cm;
    }
}
