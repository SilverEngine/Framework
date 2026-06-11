<?php
declare(strict_types=1);

namespace Silver\Auth\Access;

use Closure;
use Silver\Auth\Contracts\Authenticatable;
use Silver\Http\AuthorizationException;

/**
 * Authorization gate: named abilities + per-model policy classes.
 *
 *   Gate::define('view-dashboard', fn (?Authenticatable $u) => $u?->isAdmin());
 *   Gate::policy(Post::class, PostPolicy::class);
 *
 *   $gate->allows('update', $post);     // → PostPolicy::update($user, $post)
 *   $gate->allows('create', Post::class); // class-string works for arg-less abilities
 *   $gate->authorize('update', $post);  // throws AuthorizationException (403)
 *
 * The current user comes from the injected resolver (the container
 * default wires it to AuthManager's guard). Guests are passed as
 * null — callbacks and policy methods decide what null means; an
 * unknown ability always denies.
 *
 * Registered as a container singleton; `gate()` is the helper.
 */
final class Gate
{
    /** @var array<string, callable> ability → callback(user, ...args): bool */
    private array $abilities = [];

    /** @var array<class-string, class-string> model → policy class */
    private array $policies = [];

    /** @var array<class-string, object> memoised policy instances */
    private array $policyInstances = [];

    /** @param (Closure(): ?Authenticatable)|null $userResolver */
    public function __construct(
        private ?Closure $userResolver = null,
    ) {}

    /** @param Closure(): ?Authenticatable $resolver */
    public function setUserResolver(Closure $resolver): void
    {
        $this->userResolver = $resolver;
    }

    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    /**
     * @param class-string $modelClass
     * @param class-string $policyClass
     */
    public function policy(string $modelClass, string $policyClass): void
    {
        $this->policies[$modelClass] = $policyClass;
    }

    public function allows(string $ability, mixed ...$args): bool
    {
        return $this->check($this->resolveUser(), $ability, $args);
    }

    public function denies(string $ability, mixed ...$args): bool
    {
        return !$this->allows($ability, ...$args);
    }

    /** @throws AuthorizationException when the ability is denied */
    public function authorize(string $ability, mixed ...$args): void
    {
        if (!$this->allows($ability, ...$args)) {
            throw new AuthorizationException();
        }
    }

    /**
     * A gate view pinned to $user — shares ability/policy tables with
     * this instance, only the user resolver differs.
     */
    public function forUser(?Authenticatable $user): self
    {
        $clone = clone $this;
        $clone->userResolver = static fn (): ?Authenticatable => $user;
        return $clone;
    }

    /** @param list<mixed> $args */
    public function check(?Authenticatable $user, string $ability, array $args): bool
    {
        if (isset($this->abilities[$ability])) {
            return (bool) ($this->abilities[$ability])($user, ...$args);
        }

        $policy = $this->policyFor($args[0] ?? null);
        if ($policy !== null && method_exists($policy, $ability)) {
            // A class-string arg only selects the policy; it isn't a
            // value the policy method wants.
            if (is_string($args[0])) {
                array_shift($args);
            }
            return (bool) $policy->{$ability}($user, ...$args);
        }

        return false;
    }

    /** Resolve the policy instance for the first ability argument, if mapped. */
    private function policyFor(mixed $subject): ?object
    {
        $class = match (true) {
            is_object($subject) => $subject::class,
            is_string($subject) && isset($this->policies[$subject]) => $subject,
            default => null,
        };
        if ($class === null || !isset($this->policies[$class])) {
            return null;
        }
        return $this->policyInstances[$class] ??= new $this->policies[$class]();
    }

    private function resolveUser(): ?Authenticatable
    {
        return $this->userResolver !== null ? ($this->userResolver)() : null;
    }
}
