<?php
declare(strict_types=1);

namespace Silver\Auth\Providers;

use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Contracts\UserProvider;
use Silver\Auth\Hash;

/**
 * Looks users up via a {@see \Silver\Orm\Model\Model} subclass.
 *
 * Configured in `config/auth.php → providers.<name>` with
 * `'driver' => 'orm', 'model' => App\Models\Users::class,
 *  'username_field' => 'email'`.
 *
 * Opportunistic rehash: when validateCredentials sees a hash whose
 * parameters fall behind the current config (e.g. cost bumped), the
 * fresh hash gets written back transparently on the next successful
 * login. Users do not have to reset their passwords to roll cost.
 */
final class OrmUserProvider implements UserProvider
{
    /**
     * @param class-string<Authenticatable> $model
     */
    public function __construct(
        private readonly string $model,
        private readonly string $usernameField = 'email',
    ) {}

    public function retrieveById(int|string $id): ?Authenticatable
    {
        $cls = $this->model;
        $row = $cls::find($id);
        return $row instanceof Authenticatable ? $row : null;
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (!isset($credentials[$this->usernameField])) {
            return null;
        }
        $cls = $this->model;
        $row = $cls::query()
            ->where($this->usernameField, '=', $credentials[$this->usernameField])
            ->first();
        return $row instanceof Authenticatable ? $row : null;
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = (string) ($credentials['password'] ?? '');
        $hash  = $user->getAuthPasswordHash();
        if (!Hash::check($plain, $hash)) {
            return false;
        }

        // Opportunistic re-hash if cost params drifted.
        if (Hash::needsRehash($hash) && is_object($user) && property_exists($user, 'password')) {
            $user->password = Hash::make($plain);
            if (method_exists($user, 'save')) {
                $user->save();
            }
        }

        return true;
    }
}
