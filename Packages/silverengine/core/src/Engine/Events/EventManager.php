<?php
declare(strict_types=1);

namespace Silver\Engine\Events;

class EventManager
{
    private array $handlers = [];

    public function attach(string $name): void
    {
        $this->handlers[] = $name;
    }

    public function set(string $name, mixed $payload = null): mixed
    {
        if (!$name) {
            return false;
        }

        // Check if the event service provider has this method
        $providerClass = 'App\\Service\\EventServiceProvider';
        if (class_exists($providerClass) && method_exists($providerClass, $name)) {
            $provider = new $providerClass();
            return $provider->$name($payload);
        }

        return false;
    }

    public function detach(string $name): void
    {
        $key = array_search($name, $this->handlers, true);
        if ($key !== false) {
            unset($this->handlers[$key]);
            $this->handlers = array_values($this->handlers);
        }
    }

    public function clean(): void
    {
        $this->handlers = [];
    }

    public function fire(): mixed
    {
        $providerClass = 'App\\Service\\EventServiceProvider';

        foreach ($this->handlers as $handler) {
            if (class_exists($providerClass) && method_exists($providerClass, $handler)) {
                $provider = new $providerClass();
                return $provider->$handler();
            }
        }

        return null;
    }
}
