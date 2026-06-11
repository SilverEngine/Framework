<?php
declare(strict_types=1);

namespace Silver\Events;

/**
 * Minimal typed event dispatcher — the framework's one pub-sub story.
 *
 * Two complementary primitives exist and stay separate:
 *  - {@see \Silver\Core\Hook} — AOP interception around a *method call*
 *    (before/after/afterDeferred on class::method)
 *  - this Dispatcher — named application events with N listeners
 *
 * String events carry an optional payload; object events use the
 * object's class name as the event and the object itself as payload:
 *
 *     $events->listen(UserRegistered::class, fn ($e) => ...);
 *     $events->dispatch(new UserRegistered($user));
 *
 * Container singleton — resolve via `app(Dispatcher::class)`.
 */
final class Dispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * Fire an event. Listeners run in registration order; their return
     * values are collected and returned.
     *
     * @return list<mixed>
     */
    public function dispatch(string|object $event, mixed $payload = null): array
    {
        if (is_object($event)) {
            $payload = $event;
            $event   = $event::class;
        }

        $results = [];
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $results[] = $listener($payload);
        }
        return $results;
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    public function flush(): void
    {
        $this->listeners = [];
    }
}
