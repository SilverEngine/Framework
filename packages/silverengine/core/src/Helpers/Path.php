<?php
declare(strict_types=1);

namespace Silver\Helpers;

use Silver\Exception\Exception;

final class Path
{
    private string $jail = '';
    private string $path = '';

    public function __construct(string $path, string $jail = '')
    {
        if ($jail !== '') {
            $this->setJail($jail);
        }
        $this->setPath($path);
    }

    public function cd(string $path): static
    {
        return $path !== '' ? new static($this->getPath() . $path, $this->getJail()) : $this;
    }

    public function path(): string
    {
        return $this->real($this->path, $this->jail, true);
    }

    public function __toString(): string
    {
        return $this->path();
    }

    public function file(string $file): string
    {
        $dest = $this->real($file, $this->path(), true);

        if (!is_file($dest)) {
            throw new Exception("Destination is not a file.");
        }

        return $dest;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getJail(): string
    {
        return $this->jail;
    }

    private function setPath(string $path): void
    {
        $path = trim($path);
        if ($path !== '') {
            if (!str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = $this->getPath() . $path;
            }
            $this->path = $this->real($path, $this->getJail());
        }
    }

    private function setJail(string $jail): void
    {
        $jail = trim($jail);
        if ($jail !== '') {
            $this->jail = $this->real($jail);
        }
    }

    private function real(string $path, string $jail = '', bool $fullpath = false): string
    {
        if ($jail !== '') {
            $jail = $this->real($jail);
        }

        $resolved = realpath($jail . $path);

        if ($resolved === false) {
            throw new Exception("Non-existing path: $path");
        }

        if (is_dir($resolved)) {
            $resolved .= DIRECTORY_SEPARATOR;
        }

        if ($jail !== '') {
            if (str_starts_with($resolved, $jail)) {
                if (!$fullpath) {
                    $resolved = substr($resolved, strlen($jail));
                }
            } else {
                throw new Exception("Path is outside of jail.");
            }
        }

        return $resolved;
    }
}
