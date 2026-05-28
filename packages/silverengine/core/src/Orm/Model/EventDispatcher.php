<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

/**
 * Fans model events out to the observers registered via #[ObservedBy].
 *
 * The observer-resolver closure is injected so production wires it
 * through the IoC container (`fn ($class) => app($class)`) and tests
 * can swap in plain `new $class`.
 */
final readonly class EventDispatcher
{
    /** @var \Closure(class-string): object */
    private \Closure $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver !== null
            ? $resolver(...)
            : static fn (string $class): object => new $class();
    }

    public function dispatch(string $event, Model $model): void
    {
        $meta = AttributeRegistry::for($model::class);
        foreach ($meta->observers as $observerClass) {
            $observer = ($this->resolver)($observerClass);
            if (method_exists($observer, $event)) {
                $observer->{$event}($model);
            }
        }
    }
}
