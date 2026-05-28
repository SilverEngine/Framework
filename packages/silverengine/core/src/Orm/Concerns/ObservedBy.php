<?php
declare(strict_types=1);

namespace Silver\Orm\Concerns;

use Attribute;

/**
 * Wire an observer class to a model. The observer's methods are
 * called as model events fire:
 *
 *   class UserObserver {
 *       public function creating(User $u): void { … }
 *       public function created(User $u):  void { … }
 *       public function updating(User $u): void { … }
 *       public function updated(User $u):  void { … }
 *       public function deleting(User $u): void { … }
 *       public function deleted(User $u):  void { … }
 *   }
 *
 * The observer is resolved through the container so it gets
 * constructor injection. Multiple #[ObservedBy] attributes are
 * supported — events fan out to all of them in declaration order.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ObservedBy
{
    public function __construct(
        public string $observer,
    ) {}
}
