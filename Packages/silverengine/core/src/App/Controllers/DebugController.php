<?php
declare(strict_types=1);

namespace System\App\Controllers;

use Silver\Core\Controller;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Http\View;
use Silver\Support\DebugTimer;

class DebugController extends Controller
{
    public function index(): View|array
    {
        DebugTimer::mark('controller start', 'controller');

        $data = [
            'performance' => $this->performance(),
            'environment' => $this->environment(),
            'routes'      => $this->routes(),
            'database'    => $this->database(),
            'request'     => $this->request(),
            'config'      => $this->config(),
            'packages'    => $this->packages(),
            'server'      => $this->server(),
            'timeline'    => DebugTimer::timeline(),
            'files'       => DebugTimer::files(),
            'totalMs'     => DebugTimer::totalMs(),
        ];

        DebugTimer::mark('controller end', 'controller');

        if (($_GET['output'] ?? '') === 'json') {
            header('Content-Type: application/json');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        return View::make('debug.index', $data);
    }

    private function performance(): array
    {
        $bootMs = defined('APP_BOOT_MS') ? APP_BOOT_MS : null;
        $totalMs = defined('APP_START') ? (hrtime(true) - APP_START) / 1e6 : null;

        return [
            'Total time'       => $totalMs !== null ? number_format($totalMs, 2) . ' ms' : 'N/A',
            'Boot time'        => $bootMs !== null ? number_format($bootMs, 2) . ' ms' : 'N/A',
            'Memory usage'     => $this->formatBytes(memory_get_usage()),
            'Peak memory'      => $this->formatBytes(memory_get_peak_usage()),
            'Included files'   => (string) count(get_included_files()),
        ];
    }

    private function environment(): array
    {
        return [
            'APP_ENV'        => Env::name(),
            'APP_DEBUG'      => env('APP_DEBUG') ? 'true' : 'false',
            'APP_KEY'        => str_repeat('*', min(strlen((string) env('APP_KEY', '')), 16)) ?: '(not set)',
            'PHP version'    => PHP_VERSION,
            'PHP SAPI'       => PHP_SAPI,
            'Extensions'     => implode(', ', get_loaded_extensions()),
            'Timezone'       => date_default_timezone_get(),
            'Max execution'  => ini_get('max_execution_time') . 's',
            'Memory limit'   => ini_get('memory_limit'),
            'OPcache'        => function_exists('opcache_get_status') && opcache_get_status() ? 'enabled' : 'disabled',
        ];
    }

    private function routes(): array
    {
        $routes = [];
        foreach (Route::all() as $route) {
            $action = $route->action();
            $routes[] = [
                'method'     => strtoupper($route->method()),
                'path'       => $route->route(),
                'action'     => is_string($action) ? $action : '(Closure)',
                'name'       => $route->name() ?? '-',
                'middleware'  => $route->middleware(),
            ];
        }
        return $routes;
    }

    private function database(): array
    {
        $db = Env::get('databases');
        if (!$db || !$db->on) {
            return ['status' => 'disabled'];
        }

        return [
            'Driver'         => $db->local->driver ?? 'unknown',
            'Database'       => $db->local->database ?? $db->local->basename ?? 'unknown',
            'Host'           => $db->local->hostname ?? 'N/A',
            'Status'         => 'connected',
            'Connections'    => implode(', ', \Silver\Database\Query::connections()),
        ];
    }

    private function request(): array
    {
        $app = \Silver\Core\App::instance();
        $req = $app->instances()->get(\Silver\Http\Request::class);

        return [
            'URI'            => $req?->getUri() ?? $_SERVER['REQUEST_URI'] ?? '/',
            'Method'         => $req?->method() ?? $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'IP'             => $req?->ip() ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'User-Agent'     => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'Accept'         => $_SERVER['HTTP_ACCEPT'] ?? '*/*',
            'Session ID'     => session_id() ?: '(none)',
            'Session data'   => !empty($_SESSION['data']) ? json_encode($_SESSION['data']) : '(empty)',
        ];
    }

    private function config(): array
    {
        $sections = ['app', 'databases', 'mail', 'routes', 'middlewares', 'services', 'alias', 'providers', 'lang', 'database'];
        $flat = [];

        foreach ($sections as $section) {
            $data = Env::get($section);
            if ($data === null) {
                continue;
            }
            $this->flattenConfig($flat, $section, $data);
        }

        return $flat;
    }

    private function flattenConfig(array &$flat, string $prefix, mixed $data, int $depth = 0): void
    {
        // Don't recurse deeper than 2 levels — show as JSON
        if ($depth > 1 || is_scalar($data) || $data === null) {
            $flat[$prefix] = match (true) {
                is_bool($data) => $data ? 'true' : 'false',
                is_null($data) => '(null)',
                is_scalar($data) => (string) $data,
                default => json_encode($data, JSON_UNESCAPED_SLASHES),
            };
            return;
        }

        foreach ((array) $data as $key => $value) {
            $fullKey = $prefix . '.' . $key;

            if (is_object($value) || (is_array($value) && !array_is_list($value))) {
                $this->flattenConfig($flat, $fullKey, $value, $depth + 1);
            } elseif (is_array($value)) {
                $flat[$fullKey] = json_encode($value, JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $flat[$fullKey] = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $flat[$fullKey] = '(null)';
            } else {
                $flat[$fullKey] = (string) $value;
            }
        }
    }

    private function packages(): array
    {
        $lockFile = ROOT . 'composer.lock';
        if (!file_exists($lockFile)) {
            return ['(composer.lock not found)' => ''];
        }

        $lock = json_decode(file_get_contents($lockFile), true);
        $packages = [];
        foreach ($lock['packages'] ?? [] as $pkg) {
            $packages[$pkg['name']] = $pkg['version'];
        }
        ksort($packages);
        return $packages;
    }

    private function server(): array
    {
        return [
            'OS'             => PHP_OS . ' ' . php_uname('r'),
            'Architecture'   => php_uname('m'),
            'Server software'=> $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'Document root'  => $_SERVER['DOCUMENT_ROOT'] ?? ROOT . 'public',
            'Framework root' => ROOT,
            'PHP binary'     => PHP_BINARY,
            'Composer'       => file_exists(ROOT . 'composer.lock') ? 'present' : 'missing',
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
