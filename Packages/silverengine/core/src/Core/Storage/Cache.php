<?php
declare(strict_types=1);

namespace Silver\Core\Storage;

use Silver\Core\Env;

final class Cache
{
    private string $ext;
    private string $path;
    private ?int $expireTime;
    private bool $enabled;

    public function __construct(?array $config = null)
    {
        $config ??= (array) Env::get('caches');

        $this->ext = $config['ext'] ?? '.cache';
        $this->path = $config['path'] ?? ROOT . 'Storage/Caches/';
        $this->expireTime = isset($config['expire_time']) ? (int) $config['expire_time'] : null;
        $this->enabled = (bool) ($config['enabled'] ?? false);
    }

    public function set(string $key, mixed $data, ?int $time = null): void
    {
        $path = $this->path . 'Session/' . md5($key) . $this->ext;
        $time ??= $this->expireTime;

        file_put_contents($path, serialize([
            'expire' => $time ? time() + $time : null,
            'data'   => $data,
        ]));
    }

    public function file(string $key, string $content): void
    {
        $path = $this->path . 'Views/' . md5($key) . $this->ext;
        file_put_contents($path, $content);
    }

    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }

        $path = $this->path . 'Session/' . md5($key) . $this->ext;

        if (!file_exists($path)) {
            return null;
        }

        $data = unserialize(file_get_contents($path));

        if (isset($data['expire']) && $data['expire'] && $data['expire'] < time()) {
            unlink($path);
            return null;
        }

        return $data['data'];
    }

    public function getFile(string $key, ?int $time = null): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $path = $this->path . 'Views/' . md5($key) . $this->ext;

        if (!file_exists($path)) {
            return null;
        }

        if ($time !== null && filemtime($path) + $time < time()) {
            unlink($path);
            return null;
        }

        return file_get_contents($path);
    }

    public function cacheFile(string $key, callable $fn, ?int $time = null): string
    {
        $data = $this->getFile($key, $time);

        if ($data !== null) {
            return $data;
        }

        $data = $fn();
        $this->file($key, $data);
        return $data;
    }

    public function cache(string $key, callable $fn, ?int $time = null): mixed
    {
        $data = $this->get($key);

        if ($data !== null) {
            return $data;
        }

        $data = $fn();
        $this->set($key, $data, $time);
        return $data;
    }
}
