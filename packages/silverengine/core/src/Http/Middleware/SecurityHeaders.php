<?php
declare(strict_types=1);

namespace Silver\Http\Middleware;

use Closure;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Baseline security response headers (config/Security.php, override
 * per-app). Sane defaults out of the box:
 *
 *   X-Frame-Options: SAMEORIGIN
 *   X-Content-Type-Options: nosniff
 *   Referrer-Policy: strict-origin-when-cross-origin
 *   Strict-Transport-Security (HTTPS requests only)
 *
 * Content-Security-Policy and Permissions-Policy ship disabled (null)
 * — both are too app-specific to default safely; set them in config
 * when the app is ready for them.
 *
 * A `null` config value omits that header. Headers already present on
 * the Response (a controller's per-route CSP, say) are never
 * clobbered — this middleware only fills gaps.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    private const array DEFAULTS = [
        'x-frame-options'         => 'SAMEORIGIN',
        'x-content-type-options'  => 'nosniff',
        'referrer-policy'         => 'strict-origin-when-cross-origin',
        'content-security-policy' => null,
        'permissions-policy'      => null,
        'hsts'                    => [],
    ];

    private const array HSTS_DEFAULTS = [
        'enabled'            => true,
        'max-age'            => 31536000,
        'include-subdomains' => true,
        'preload'            => false,
    ];

    /** Config key → header name. */
    private const array HEADER_NAMES = [
        'x-frame-options'         => 'X-Frame-Options',
        'x-content-type-options'  => 'X-Content-Type-Options',
        'referrer-policy'         => 'Referrer-Policy',
        'content-security-policy' => 'Content-Security-Policy',
        'permissions-policy'      => 'Permissions-Policy',
    ];

    /** @var array<string, mixed> */
    private readonly array $config;

    /** @param array<string, mixed>|null $config explicit config (tests); null = config/Security.php */
    public function __construct(?array $config = null)
    {
        $config       = ($config ?? Env::get('security', [])) + self::DEFAULTS;
        $config['hsts'] = ((array) $config['hsts']) + self::HSTS_DEFAULTS;
        $this->config = $config;
    }

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        foreach (self::HEADER_NAMES as $key => $header) {
            $value = $this->config[$key];
            if (is_string($value) && $res->getHeader($header) === null) {
                $res->setHeader($header, $value);
            }
        }

        // HSTS over plain HTTP is ignored by browsers — don't emit it.
        $hsts = $this->config['hsts'];
        if ((bool) $hsts['enabled']
            && $req->isSecure()
            && $res->getHeader('Strict-Transport-Security') === null) {
            $res->setHeader('Strict-Transport-Security', $this->hstsValue($hsts));
        }

        return $next();
    }

    /** @param array<string, mixed> $hsts */
    private function hstsValue(array $hsts): string
    {
        $value = 'max-age=' . (int) $hsts['max-age'];
        if ((bool) $hsts['include-subdomains']) {
            $value .= '; includeSubDomains';
        }
        if ((bool) $hsts['preload']) {
            $value .= '; preload';
        }
        return $value;
    }
}
