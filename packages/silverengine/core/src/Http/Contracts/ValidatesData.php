<?php
declare(strict_types=1);

namespace Silver\Http\Contracts;

/**
 * Marker for objects that want {@see \Silver\Core\DI::call()} to run
 * their validation lifecycle before they reach the controller action.
 *
 * {@see \Silver\Http\FormRequest} is the canonical implementor;
 * anything else can implement this interface to plug into the same
 * pre-action validate-and-throw pipeline.
 */
interface ValidatesData
{
    public function validateResolved(): void;
}
