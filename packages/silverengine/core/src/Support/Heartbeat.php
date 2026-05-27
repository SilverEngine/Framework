<?php
declare(strict_types=1);

namespace Silver\Support;

use PDO;
use Throwable;
use Silver\Core\App;
use Silver\Core\Env;
use Silver\Core\Hook;
use Silver\Core\Route;
use Silver\Database\Db;
use Silver\Engine\Events\EventManager;
use Silver\FileSystem\FileSystem;

/**
 * Framework self-check. Runs a fixed battery of probes that exercise the
 * critical subsystems — config, container, autoloader, routes, middleware
 * pipeline, providers, storage, database — and rolls them up into a single
 * "ok | degraded | down" verdict.
 *
 * Each check is independent: a single failure degrades the report but
 * doesn't short-circuit the others. The output shape is designed for ops
 * dashboards and load-balancer health probes.
 */
final class Heartbeat
{
    /** Minimum PHP version this framework requires. */
    private const MIN_PHP = '8.4.0';

    /**
     * @return array{
     *     status: 'ok'|'degraded'|'down',
     *     php: string,
     *     env: string,
     *     debug: bool,
     *     elapsed_ms: float,
     *     checks: list<array{name:string,status:'ok'|'warn'|'fail',detail:string}>,
     *     performance: array<string,mixed>
     * }
     */
    public function run(): array
    {
        $started = microtime(true);
        $checks = [
            $this->checkPhp(),
            $this->checkConfig(),
            $this->checkContainer(),
            $this->checkRoutes(),
            $this->checkMiddlewares(),
            $this->checkProviders(),
            $this->checkStorage(),
            $this->checkDatabase(),
            $this->checkAssets(),
        ];

        $status = 'ok';
        foreach ($checks as $c) {
            if ($c['status'] === 'fail') { $status = 'down'; break; }
            if ($c['status'] === 'warn' && $status === 'ok') { $status = 'degraded'; }
        }

        return [
            'status'      => $status,
            'php'         => PHP_VERSION,
            'env'         => Env::name(),
            'debug'       => (bool) Env::get('debug'),
            'elapsed_ms'  => round((microtime(true) - $started) * 1000, 2),
            'checks'      => $checks,
            'performance' => $this->measurePerformance(),
        ];
    }

    /**
     * Synthetic timings + memory snapshot. Each subsystem is exercised
     * independently — the numbers are repeatable and useful for tracking
     * cold-path regressions over time.
     *
     * @return array<string,mixed>
     */
    private function measurePerformance(): array
    {
        $requestStart = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $now = microtime(true);

        return [
            'request' => [
                'elapsed_ms'    => round(($now - $requestStart) * 1000, 2),
                'boot_ms'       => defined('APP_BOOT_MS') ? round(APP_BOOT_MS, 2) : null,
            ],
            'lifecycle'      => $this->lifecycle(),
            'last_completed' => $this->lastCompletedRequest(),
            'subsystems' => [
                'config_lookup_us'         => $this->microTime(fn () => $this->exerciseConfig()),
                'container_resolve_us'     => $this->microTime(fn () => $this->exerciseContainer()),
                'middleware_instantiate_us' => $this->microTime(fn () => $this->exerciseMiddleware()),
                'provider_instantiate_us'   => $this->microTime(fn () => $this->exerciseProviders()),
                'route_table_us'            => $this->microTime(fn () => $this->exerciseRoutes()),
                'request_object_us'         => $this->microTime(fn () => $this->exerciseRequest()),
            ],
            'memory' => [
                'current_mb' => round(memory_get_usage(true) / 1048576, 2),
                'peak_mb'    => round(memory_get_peak_usage(true) / 1048576, 2),
                'limit'      => ini_get('memory_limit') ?: 'unknown',
            ],
            'opcache' => $this->opcacheStats(),
        ];
    }

    /**
     * Run a closure N times and return the median microsecond cost,
     * rounded to int. Median (not mean) so a single GC pause doesn't
     * skew the report.
     */
    private function microTime(callable $fn, int $iterations = 5): int
    {
        $samples = [];
        for ($i = 0; $i < $iterations; $i++) {
            $t = microtime(true);
            $fn();
            $samples[] = (microtime(true) - $t) * 1_000_000;
        }
        sort($samples);
        return (int) round($samples[(int) ($iterations / 2)]);
    }

    private function exerciseConfig(): void
    {
        Env::get('app.name');
        Env::get('recorder.limit');
        Env::get('middlewares');
        Env::get('routes');
    }

    private function exerciseContainer(): void
    {
        $c = App::instance()->instances();
        $c->make(Hook::class);
        $c->make(EventManager::class);
        $c->make(FileSystem::class);
        $c->make(\Silver\Core\Route::class);
    }

    private function exerciseMiddleware(): void
    {
        $c = App::instance()->instances();
        foreach ((array) Env::get('middlewares', []) as $cls) {
            if (is_string($cls) && class_exists($cls)) {
                $c->make($cls);
            }
        }
    }

    private function exerciseProviders(): void
    {
        foreach ((array) Env::get('providers', []) as $cls) {
            if (is_string($cls) && class_exists($cls)) {
                // Construct only — don't fire before()/after() side effects.
                new $cls(null);
            }
        }
    }

    private function exerciseRoutes(): void
    {
        app(\Silver\Core\Route::class)->all();
    }

    private function exerciseRequest(): void
    {
        // Touch the request object's commonly-used surface so cold cache
        // costs (header parsing, URI splitting) get measured.
        $req = \Silver\Http\Request::instance();
        if ($req !== null) {
            $req->method();
            $req->getUri();
            $req->headerValue('Accept');
        }
    }

    /**
     * Pull the actual per-phase timeline of THIS request from the
     * DebugTimer. Heartbeat fires inside the controller action, so the
     * picture covers everything from autoload through middleware down to
     * the controller call — but not response/send (those run after).
     *
     * Spans are grouped by category (boot, kernel, middleware, controller,
     * request, view) and summed; useful for ops dashboards that want a
     * per-phase delta at a glance instead of a fat timeline.
     *
     * @return array<string,mixed>
     */
    private function lifecycle(): array
    {
        $dt = function_exists('dt') ? dt() : null;
        if ($dt === null || !$dt->enabled()) {
            return ['enabled' => false];
        }

        $byCategory = [];
        $spans = [];
        foreach ($dt->timeline() as $e) {
            $cat = $e['category'] ?? 'uncategorised';
            if ($e['type'] === 'span') {
                $inProg = !empty($e['in_progress']);
                // Don't double-count open spans into category totals — they
                // would otherwise inflate `kernel` by the wrapping span
                // "middlewares + controller" plus the controller frame.
                if (!$inProg) {
                    $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $e['duration_ms'];
                }
                $spans[] = [
                    'category'    => $cat,
                    'label'       => $e['label'],
                    'ms'          => round($e['duration_ms'], 3),
                    'start_ms'    => round($e['start_ms'], 3),
                    'end_ms'      => round($e['end_ms'], 3),
                    'in_progress' => $inProg,
                ];
            }
        }
        // Sort the chart by actual start time so the waterfall reads
        // top-to-bottom in chronological order.
        usort($spans, static fn ($a, $b) => $a['start_ms'] <=> $b['start_ms']);
        ksort($byCategory);
        foreach ($byCategory as $k => $v) {
            $byCategory[$k] = round($v, 3);
        }

        return [
            'enabled'      => true,
            'total_ms'     => round($dt->totalMs(), 3),
            'by_category'  => $byCategory,
            'spans'        => $spans,
        ];
    }

    /**
     * Load the most recent recording from storage/debug/recordings/. Gives
     * us a 100% closed-span view of what a real request looks like — which
     * the live `lifecycle` block can't, because heartbeat fires mid-request
     * (the view render / deferred hooks / finalize providers all happen
     * *after* this controller returns).
     *
     * Filenames are sortable: `{epoch_ms}-{rand}.json`.
     *
     * @return array<string,mixed>
     */
    private function lastCompletedRequest(): array
    {
        $dir = \ROOT . 'storage/debug/recordings';
        if (!is_dir($dir)) {
            return ['enabled' => false];
        }
        $files = glob($dir . '/*.json') ?: [];
        if ($files === []) {
            return ['enabled' => false];
        }
        sort($files);
        $latest = end($files);

        $raw = @file_get_contents($latest);
        if ($raw === false) {
            return ['enabled' => false];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['timeline'])) {
            return ['enabled' => false];
        }

        $byCategory = [];
        $spans = [];
        foreach ($data['timeline'] as $e) {
            if (($e['type'] ?? '') !== 'span') {
                continue;
            }
            $cat = $e['category'] ?? 'uncategorised';
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + (float) $e['duration_ms'];
            $spans[] = [
                'category' => $cat,
                'label'    => $e['label'],
                'ms'       => round((float) $e['duration_ms'], 3),
                'start_ms' => round((float) $e['start_ms'], 3),
                'end_ms'   => round((float) $e['end_ms'], 3),
            ];
        }
        usort($spans, static fn ($a, $b) => $a['start_ms'] <=> $b['start_ms']);
        ksort($byCategory);
        foreach ($byCategory as $k => $v) {
            $byCategory[$k] = round($v, 3);
        }

        return [
            'enabled'     => true,
            'id'          => (string) ($data['id'] ?? ''),
            'at'          => (string) ($data['at'] ?? ''),
            'method'      => (string) ($data['method'] ?? ''),
            'path'        => (string) ($data['path'] ?? ''),
            'status'      => (int) ($data['status'] ?? 0),
            'total_ms'    => round((float) ($data['total_ms'] ?? 0), 3),
            'by_category' => $byCategory,
            'spans'       => $spans,
        ];
    }

    /** @return array<string,mixed> */
    private function opcacheStats(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }
        $s = @opcache_get_status(false);
        if (!is_array($s) || empty($s['opcache_enabled'])) {
            return ['enabled' => false];
        }
        $mem = $s['memory_usage'] ?? [];
        $stats = $s['opcache_statistics'] ?? [];
        return [
            'enabled'        => true,
            'used_mb'        => isset($mem['used_memory']) ? round($mem['used_memory'] / 1048576, 2) : null,
            'free_mb'        => isset($mem['free_memory']) ? round($mem['free_memory'] / 1048576, 2) : null,
            'hit_rate_pct'   => isset($stats['opcache_hit_rate']) ? round((float) $stats['opcache_hit_rate'], 2) : null,
            'cached_scripts' => $stats['num_cached_scripts'] ?? null,
        ];
    }

    /** @return array{name:string,status:'ok'|'warn'|'fail',detail:string} */
    private function checkPhp(): array
    {
        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            return $this->fail('php', "PHP " . PHP_VERSION . " < required " . self::MIN_PHP);
        }
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
        $opcacheNote = ($opcache && !empty($opcache['opcache_enabled'])) ? 'opcache on' : 'opcache off';
        return $this->ok('php', 'PHP ' . PHP_VERSION . ' · ' . $opcacheNote);
    }

    private function checkConfig(): array
    {
        try {
            $envName = Env::name();
            if ($envName === '') {
                return $this->fail('config', 'APP_ENV not set');
            }
            // Touch a few keys that should resolve in any install. Config
            // values come through as stdClass (assoc) or array (list) — cast
            // before counting so either shape works.
            if (Env::get('routes', null) === null) {
                return $this->warn('config', 'routes config missing');
            }
            if (Env::get('middlewares', null) === null) {
                return $this->warn('config', 'middlewares config missing');
            }
            return $this->ok('config', "loaded · env={$envName}");
        } catch (Throwable $e) {
            return $this->fail('config', $e->getMessage());
        }
    }

    private function checkContainer(): array
    {
        try {
            $container = App::instance()->instances();
            // Resolve a handful of services to prove autowiring works.
            $container->make(Hook::class);
            $container->make(EventManager::class);
            $container->make(FileSystem::class);
            $container->make(Route::class);
            return $this->ok('container', 'IoC resolves core services');
        } catch (Throwable $e) {
            return $this->fail('container', $e->getMessage());
        }
    }

    private function checkRoutes(): array
    {
        try {
            $router = app(Route::class);
            $count = count($router->all());
            if ($count === 0) {
                return $this->warn('routes', 'no routes registered');
            }
            $cached = is_file(\ROOT . 'storage/cache/routes.php');
            // Cache miss is fine in local (you're editing routes constantly);
            // outside local it means `php silver optimize` wasn't run on
            // deploy, leaving boot ~0.25ms slower than it needs to be.
            if (!$cached && Env::name() !== 'local') {
                return $this->warn(
                    'routes',
                    "{$count} routes · cache miss — run `php silver optimize` to cache",
                );
            }
            return $this->ok('routes', "{$count} routes · cache " . ($cached ? 'hit' : 'miss'));
        } catch (Throwable $e) {
            return $this->fail('routes', $e->getMessage());
        }
    }

    private function checkMiddlewares(): array
    {
        // Configs with int-keyed entries materialise as stdClass; cast so we
        // can count + iterate uniformly.
        $list = (array) Env::get('middlewares', []);
        $count = count($list);
        if ($count === 0) {
            return $this->warn('middlewares', 'pipeline is empty');
        }
        foreach ($list as $cls) {
            if (!is_string($cls) || !class_exists($cls)) {
                return $this->fail('middlewares', "missing class: " . (string) $cls);
            }
        }
        return $this->ok('middlewares', "{$count} registered");
    }

    private function checkProviders(): array
    {
        $list = (array) Env::get('providers', []);
        $count = count($list);
        foreach ($list as $cls) {
            if (!is_string($cls) || !class_exists($cls)) {
                return $this->fail('providers', "missing class: " . (string) $cls);
            }
        }
        return $this->ok('providers', $count === 0 ? 'none configured' : "{$count} registered");
    }

    private function checkStorage(): array
    {
        $paths = [
            'storage/cache'             => \ROOT . 'storage/cache',
            'storage/debug/recordings'  => \ROOT . 'storage/debug/recordings',
        ];
        foreach ($paths as $label => $abs) {
            if (!is_dir($abs)) {
                // Lazy-create — same behavior as the framework's own writers.
                @mkdir($abs, 0o775, true);
            }
            if (!is_dir($abs) || !is_writable($abs)) {
                return $this->fail('storage', "{$label} not writable");
            }
        }
        return $this->ok('storage', 'cache + recordings writable');
    }

    private function checkDatabase(): array
    {
        $configured = Env::get('databases', null);
        if ($configured === null) {
            return $this->warn('database', 'no connections configured');
        }
        // Connections are opt-in via the `on` flag — when off, skip the ping
        // rather than booting a driver the user hasn't asked for.
        if (Env::get('databases.on') === false) {
            return $this->ok('database', 'configured · ping disabled (databases.on=false)');
        }
        try {
            $pdo = Db::connection();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $pdo->query('SELECT 1');
            return $this->ok('database', "ping ok · driver={$driver}");
        } catch (Throwable $e) {
            return $this->fail('database', $e->getMessage());
        }
    }

    private function checkAssets(): array
    {
        $manifest = \ROOT . 'public/build/.vite/manifest.json';
        if (Env::name() === 'local') {
            return $this->ok('assets', 'dev mode — manifest not required');
        }
        if (!is_file($manifest)) {
            return $this->warn('assets', 'public/build/.vite/manifest.json missing (run `npm run build`)');
        }
        return $this->ok('assets', 'vite manifest present');
    }

    /** @return array{name:string,status:'ok',detail:string} */
    private function ok(string $name, string $detail): array { return ['name' => $name, 'status' => 'ok',   'detail' => $detail]; }
    private function warn(string $name, string $detail): array { return ['name' => $name, 'status' => 'warn', 'detail' => $detail]; }
    private function fail(string $name, string $detail): array { return ['name' => $name, 'status' => 'fail', 'detail' => $detail]; }
}
