<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Core\Contracts\InstanceInterface;

class App implements InstanceInterface
{
    private static ?self $current = null;
    private string $path = 'app/';
    private Container $instances;

    public function __construct()
    {
        $this->instances = new Container();
        $this->bindFrameworkDefaults();
    }

    /**
     * Wire the framework's built-in services. Each binding is a singleton
     * and an interface → concrete pair, so user code can rebind or
     * decorate (`$c->bind(...)` / `$c->extend(...)`) without forking.
     */
    private function bindFrameworkDefaults(): void
    {
        $this->instances->singleton(
            \Silver\FileSystem\FileSystem::class,
            \Silver\FileSystem\LocalFileSystem::class,
        );

        // Route, Config, Hook are stateful framework services resolved as
        // singletons so every caller (router, kernel, controllers, route
        // files) sees the same instance instead of the old static state.
        $this->instances->singleton(Route::class);
        $this->instances->singleton(Config::class);
        $this->instances->singleton(Hook::class);
        $this->instances->singleton(DI::class);
        $this->instances->singleton(\Silver\Orm\Connection\ConnectionManager::class);
        $this->instances->singleton(\Silver\Orm\Connection\TransactionManager::class);
        $this->instances->singleton(ErrorHandler::class);
        $this->instances->singleton(\Silver\Support\DebugTimer::class);
        $this->instances->singleton(\Silver\Support\RequestRecorder::class);

        // View composer + shared-data registry. Was static state on the
        // View class; now an instance so tests can swap a fresh one per
        // case via `Container::instance(...)`.
        $this->instances->singleton(\Silver\Http\ViewRegistry::class);
        $this->instances->singleton(\Silver\Http\Validator::class);
        $this->instances->singleton(\Silver\Http\Csrf\TokenStore::class);
        $this->instances->singleton(\Silver\Auth\AuthManager::class);
    }

    public function instances(): Container
    {
        return $this->instances;
    }

    public function register(object ...$instances): mixed
    {
        $last = null;
        foreach ($instances as $instance) {
            if (is_array($instance)) {
                foreach ($instance as $name => $inst) {
                    $last = is_numeric($name)
                        ? $this->instances->register($inst)
                        : $this->instances->registerNamed($name, $inst);
                }
            } else {
                $last = $this->instances->register($instance);
            }
        }
        return $last;
    }

    public function path(string $path = ''): string
    {
        return $this->path . $path;
    }

    public function systemPath(string $path = ''): string
    {
        return 'packages/silverengine/core/src/App/' . $path;
    }

    public function find(string $path): ?string
    {
        $target = $this->path($path);
        if (file_exists($target)) {
            return $target;
        }

        $target = $this->systemPath($path);
        if (file_exists($target)) {
            return $target;
        }

        return null;
    }

    public static function instance(): static
    {
        if (self::$current === null) {
            self::$current = new static();
        }
        return self::$current;
    }
}
