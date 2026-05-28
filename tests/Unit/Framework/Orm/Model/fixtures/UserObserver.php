<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model\fixtures;

final class UserObserver
{
    /** @var list<string> */
    public static array $events = [];

    public function creating(User $u): void { self::$events[] = 'creating:'  . $u->email; }
    public function created(User $u):  void { self::$events[] = 'created:'   . $u->email; }
    public function updating(User $u): void { self::$events[] = 'updating:'  . $u->email; }
    public function updated(User $u):  void { self::$events[] = 'updated:'   . $u->email; }
    public function deleting(User $u): void { self::$events[] = 'deleting:'  . $u->email; }
    public function deleted(User $u):  void { self::$events[] = 'deleted:'   . $u->email; }
}
