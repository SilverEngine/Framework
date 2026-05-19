<?php

declare(strict_types=1);

namespace Silver\Http;

/**
 * The HTTP methods SilverEngine routes on. Backed values are lowercase to
 * match the framework's existing string contract (`Request::method()`,
 * `Route::find($uri, $method)`), so this enum is a drop-in for the old
 * literal list with no behaviour change.
 */
enum HttpMethod: string
{
    case GET     = 'get';
    case POST    = 'post';
    case PUT     = 'put';
    case DELETE  = 'delete';
    case PATCH   = 'patch';
    case OPTIONS = 'options';

    /**
     * Parse a raw request method (any case, surrounding space tolerated).
     * Unknown or malformed input falls back to GET — the same lenient
     * contract `Request::method()` had before this enum existed.
     */
    public static function parse(string $raw): self
    {
        return self::tryFrom(strtolower(trim($raw))) ?? self::GET;
    }
}
