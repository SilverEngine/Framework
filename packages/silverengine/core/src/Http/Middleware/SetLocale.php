<?php
declare(strict_types=1);

namespace Silver\Http\Middleware;

use Closure;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\Session;
use Silver\Support\Translator;

/**
 * Locale switching (config/lang.php). Resolution order per request:
 *
 *   1. `?lang=xx` query param — switches AND persists to the session
 *      (the same `lang` session key the legacy Lang class reads)
 *   2. the session's `lang`
 *   3. the first supported tag in Accept-Language
 *   4. the configured default — i.e. do nothing
 *
 * A `lang.supported` list (when set) gates 1–3: unsupported locales
 * are ignored, never an error. Add to the global stack in
 * config/Middlewares.php or per-route via an alias.
 */
final class SetLocale
{
    public function __construct(private readonly Translator $translator) {}

    public function execute(Request $request, Response $response, Closure $next): mixed
    {
        $locale = $this->fromQuery($request)
            ?? $this->fromSession()
            ?? $this->fromAcceptLanguage($request);

        if ($locale !== null) {
            $this->translator->setLocale($locale);
        }

        return $next();
    }

    private function fromQuery(Request $request): ?string
    {
        $lang = $request->query('lang');
        if (!is_string($lang) || !$this->supported($lang)) {
            return null;
        }

        Session::set('lang', $lang);
        return $lang;
    }

    private function fromSession(): ?string
    {
        $lang = Session::get('lang');
        return is_string($lang) && $this->supported($lang) ? $lang : null;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        $header = $request->headerValue('Accept-Language');
        if ($header === null) {
            return null;
        }

        foreach (explode(',', $header) as $entry) {
            // "sl-SI;q=0.9" → "sl"
            $tag = strtolower(trim(explode(';', $entry)[0]));
            $tag = explode('-', $tag)[0];
            if ($tag !== '' && $tag !== '*' && $this->supported($tag)) {
                return $tag;
            }
        }
        return null;
    }

    private function supported(string $locale): bool
    {
        $supported = Env::get('lang.supported');
        if ($supported === null) {
            return true;
        }

        $list = array_map(strval(...), (array) $supported);
        return in_array($locale, $list, true);
    }
}
