<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use RuntimeException;

final class ModelNotFound extends RuntimeException
{
    public static function for(string $class, mixed $id): self
    {
        return new self("{$class} with key " . var_export($id, true) . ' not found.');
    }
}
