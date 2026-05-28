<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

/**
 * Two-way cast between the database column value and the PHP-side
 * property value. Implementations should be stateless — registered
 * once per (model, property), invoked many times.
 */
interface CastsAttribute
{
    /** Hydrate the DB-shaped value into the PHP property type. */
    public function get(mixed $value): mixed;

    /** Project a PHP property value back to a DB-storable scalar. */
    public function set(mixed $value): mixed;
}
