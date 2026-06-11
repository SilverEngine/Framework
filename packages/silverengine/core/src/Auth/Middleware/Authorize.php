<?php
declare(strict_types=1);

namespace Silver\Auth\Middleware;

use Closure;
use Silver\Auth\Access\Gate;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Route middleware gating on a Gate ability:
 *
 *   'can:view-dashboard'      — plain ability check
 *   'can:edit-post,post'      — forwards the {post} route variable's
 *                               value as the ability's argument
 *
 * The Gate service dep autowires through MiddlewareResolver; denial
 * throws AuthorizationException → 403.
 */
final class Authorize implements MiddlewareInterface
{
    public function __construct(
        private readonly Gate $gate,
        private readonly string $ability = '',
        private readonly string $routeParam = '',
    ) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $args = [];
        if ($this->routeParam !== '') {
            $args[] = $req->route()?->variables()[$this->routeParam] ?? null;
        }

        $this->gate->authorize($this->ability, ...$args);

        return $next();
    }
}
