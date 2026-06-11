<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Access\Gate;
use Silver\Auth\Concerns\Authorizable;
use Silver\Auth\Contracts\Authenticatable;
use Silver\Core\App;
use Silver\Http\AuthorizationException;

final class GateUser implements Authenticatable
{
    use Authorizable;

    public function __construct(public int $id = 1, public string $role = 'member') {}

    public function getAuthIdentifier(): int|string { return $this->id; }
    public function getAuthPasswordHash(): string   { return ''; }
    public function getAuthIdentifierName(): string { return 'id'; }
}

final class GatePost
{
    public function __construct(public int $authorId = 1) {}
}

final class GatePostPolicy
{
    public function update(?Authenticatable $user, GatePost $post): bool
    {
        return $user instanceof GateUser && $user->id === $post->authorId;
    }

    public function create(?Authenticatable $user): bool
    {
        return $user instanceof GateUser && $user->role === 'admin';
    }
}

final class GateTest extends TestCase
{
    private function gate(?Authenticatable $user = null): Gate
    {
        return new Gate(fn (): ?Authenticatable => $user);
    }

    public function testDefinedAbilityAllowsAndDenies(): void
    {
        $gate = $this->gate(new GateUser(role: 'admin'));
        $gate->define('view-dashboard', fn (?Authenticatable $u): bool => $u instanceof GateUser && $u->role === 'admin');

        self::assertTrue($gate->allows('view-dashboard'));
        self::assertFalse($gate->denies('view-dashboard'));

        $member = $this->gate(new GateUser(role: 'member'));
        $member->define('view-dashboard', fn (?Authenticatable $u): bool => $u instanceof GateUser && $u->role === 'admin');
        self::assertFalse($member->allows('view-dashboard'));
    }

    public function testGuestIsPassedAsNull(): void
    {
        $gate = $this->gate(null);
        $gate->define('browse', fn (?Authenticatable $u): bool => $u === null);

        self::assertTrue($gate->allows('browse'));
    }

    public function testUnknownAbilityDenies(): void
    {
        self::assertFalse($this->gate(new GateUser())->allows('does-not-exist'));
    }

    public function testPolicyMethodResolvedFromObjectArg(): void
    {
        $gate = $this->gate(new GateUser(id: 7));
        $gate->policy(GatePost::class, GatePostPolicy::class);

        self::assertTrue($gate->allows('update', new GatePost(authorId: 7)));
        self::assertFalse($gate->allows('update', new GatePost(authorId: 9)));
    }

    public function testPolicyResolvedFromClassStringArg(): void
    {
        $gate = $this->gate(new GateUser(role: 'admin'));
        $gate->policy(GatePost::class, GatePostPolicy::class);

        self::assertTrue($gate->allows('create', GatePost::class));

        $member = $this->gate(new GateUser(role: 'member'));
        $member->policy(GatePost::class, GatePostPolicy::class);
        self::assertFalse($member->allows('create', GatePost::class));
    }

    public function testAuthorizeThrowsOnDeny(): void
    {
        $gate = $this->gate(new GateUser());
        $gate->define('nuke', fn (): bool => false);

        $this->expectException(AuthorizationException::class);
        $gate->authorize('nuke');
    }

    public function testForUserOverridesResolver(): void
    {
        $gate = $this->gate(new GateUser(role: 'member'));
        $gate->define('admin-only', fn (?Authenticatable $u): bool => $u instanceof GateUser && $u->role === 'admin');

        self::assertFalse($gate->allows('admin-only'));
        self::assertTrue($gate->forUser(new GateUser(role: 'admin'))->allows('admin-only'));
        // Original instance untouched.
        self::assertFalse($gate->allows('admin-only'));
    }

    public function testAuthorizableTraitCanAndCant(): void
    {
        $gate = app(Gate::class);
        $gate->define('publish', fn (?Authenticatable $u): bool => $u instanceof GateUser && $u->role === 'admin');

        $admin  = new GateUser(role: 'admin');
        $member = new GateUser(role: 'member');

        self::assertTrue($admin->can('publish'));
        self::assertFalse($member->can('publish'));
        self::assertTrue($member->cant('publish'));
    }

    public function testGateIsAContainerSingleton(): void
    {
        self::assertSame(app(Gate::class), app(Gate::class));
        self::assertSame(app(Gate::class), App::instance()->instances()->make(Gate::class));
    }
}
