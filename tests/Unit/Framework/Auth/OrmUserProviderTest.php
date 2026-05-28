<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Hash;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Auth\fixtures\AuthUser;

final class OrmUserProviderTest extends TestCase
{
    private OrmUserProvider $provider;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();

        $cm = new ConnectionManager();
        $cm->connect('default', 'sqlite::memory:');
        $cm->setDefault('default');
        $cm->exec(
            'CREATE TABLE auth_users (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                email    TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL
            )',
        );
        Model::bind($cm, new EventDispatcher());

        AuthUser::create([
            'email'    => 'lex@example.test',
            'password' => Hash::make('s3cretp4ss'),
        ]);

        $this->provider = new OrmUserProvider(AuthUser::class, 'email');
    }

    public function testRetrieveByIdReturnsUser(): void
    {
        $user = $this->provider->retrieveById(1);
        $this->assertNotNull($user);
        $this->assertSame('lex@example.test', $user->email);
    }

    public function testRetrieveByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->provider->retrieveById(999));
    }

    public function testRetrieveByCredentialsByEmail(): void
    {
        $user = $this->provider->retrieveByCredentials(['email' => 'lex@example.test']);
        $this->assertNotNull($user);
    }

    public function testRetrieveByCredentialsWithoutUsernameFieldReturnsNull(): void
    {
        $this->assertNull($this->provider->retrieveByCredentials(['name' => 'Lex']));
    }

    public function testValidateCredentialsAcceptsCorrectPassword(): void
    {
        $user = $this->provider->retrieveByCredentials(['email' => 'lex@example.test']);
        $this->assertTrue(
            $this->provider->validateCredentials($user, ['password' => 's3cretp4ss'])
        );
    }

    public function testValidateCredentialsRejectsWrongPassword(): void
    {
        $user = $this->provider->retrieveByCredentials(['email' => 'lex@example.test']);
        $this->assertFalse(
            $this->provider->validateCredentials($user, ['password' => 'wrong'])
        );
    }
}
