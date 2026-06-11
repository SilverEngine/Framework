<?php
declare(strict_types=1);

namespace Silver\Orm\Factory;

use Closure;
use Silver\Orm\Model\Model;

/**
 * Minimal model factory — test data without a faker dependency.
 *
 *     final class UserFactory extends Factory
 *     {
 *         protected string $model = User::class;
 *
 *         public function definition(): array
 *         {
 *             return [
 *                 'email' => fn (int $i) => "user{$i}@example.test",
 *                 'name'  => 'User',
 *             ];
 *         }
 *
 *         public function admin(): static
 *         {
 *             return $this->state(['role' => 'admin']);
 *         }
 *     }
 *
 *     UserFactory::new()->count(3)->create();
 *     UserFactory::new()->admin()->make(['name' => 'Root']);
 *
 * Closure values in the definition/overrides are resolved per
 * instance and receive the 0-based build index — use them for
 * unique columns. Attributes are assigned via Model::setAttribute()
 * (mutators apply; the mass-assignment guard does NOT — factories are
 * trusted code).
 *
 * @template T of Model
 */
abstract class Factory
{
    /** @var class-string<T> */
    protected string $model;

    private int $count = 1;

    /** @var list<array<string, mixed>|Closure> */
    private array $states = [];

    /**
     * Fluent entry point. Concrete factories must keep a
     * parameterless constructor (the default — add none).
     */
    public static function new(): static
    {
        // Factories are final leaf classes with no constructors, so
        // new static() is safe; the generic narrows in the subclass.
        // @phpstan-ignore new.static, return.type
        return new static();
    }

    /** @return array<string, mixed> */
    abstract public function definition(): array;

    public function count(int $count): static
    {
        $this->count = max(1, $count);
        return $this;
    }

    /**
     * Layer attribute overrides on top of the definition. Array, or a
     * Closure receiving (currentAttributes, index) and returning the
     * overrides.
     *
     * @param array<string, mixed>|Closure $state
     */
    public function state(array|Closure $state): static
    {
        $this->states[] = $state;
        return $this;
    }

    /**
     * Build unsaved model instances.
     *
     * @param array<string, mixed> $overrides
     * @return T|list<T> single instance when count() is 1
     */
    public function make(array $overrides = []): mixed
    {
        $out = [];
        for ($i = 0; $i < $this->count; $i++) {
            $out[] = $this->makeOne($overrides, $i);
        }
        return $this->count === 1 ? $out[0] : $out;
    }

    /**
     * Build + persist.
     *
     * @param array<string, mixed> $overrides
     * @return T|list<T>
     */
    public function create(array $overrides = []): mixed
    {
        $made = $this->make($overrides);
        foreach (is_array($made) ? $made : [$made] as $model) {
            $model->save();
        }
        return $made;
    }

    /** @param array<string, mixed> $overrides */
    private function makeOne(array $overrides, int $index): Model
    {
        $attrs = $this->definition();
        foreach ($this->states as $state) {
            $attrs = array_merge(
                $attrs,
                $state instanceof Closure ? $state($attrs, $index) : $state,
            );
        }
        $attrs = array_merge($attrs, $overrides);

        /** @var class-string<Model> $class */
        $class = $this->model;
        $meta  = $class::metadata();
        $model = new $class();

        foreach ($attrs as $key => $value) {
            if (!isset($meta->properties[$key])) {
                continue; // not a column-backed property
            }
            if ($value instanceof Closure) {
                $value = $value($index);
            }
            $model->setAttribute($key, $value);
        }
        return $model;
    }
}
