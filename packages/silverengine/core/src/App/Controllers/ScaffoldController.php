<?php
declare(strict_types=1);

namespace System\App\Controllers;

use Silver\Core\App;
use Silver\Core\Controller;
use Silver\Core\Env;
use Silver\Exception\NotFoundException;
use Silver\Http\Request;
use Silver\Support\Scaffolder;

/**
 * Dev-only POST endpoint behind the 404 page's "Create page" button.
 * Refuses to run outside `APP_ENV=local` + `APP_DEBUG=true`. Idempotent:
 * re-scaffolding the same URL won't overwrite existing files.
 */
final class ScaffoldController extends Controller
{
    public function __invoke(Request $request): mixed
    {
        if (Env::name() !== 'local' || !Env::get('debug')) {
            throw new NotFoundException('Not found.');
        }

        // DI::call doesn't autowire unbound type-hints, so resolve the
        // Scaffolder via the container directly. The container will
        // autowire its FileSystem dep from App::bindFrameworkDefaults().
        $scaffolder = app(Scaffolder::class);

        $url  = (string) $request->input('url');
        $name = (string) $request->input('name');
        if ($url === '' || $name === '') {
            return $this->json(['ok' => false, 'error' => 'url and name are required'], 422);
        }

        try {
            $result = $scaffolder->scaffold($url, $name);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        // The original 404'd URL is now (or will be on next request) live —
        // redirect there so the dev sees the new page immediately.
        header('Location: ' . $result['url']);
        http_response_code(303);
        return '';
    }

    private function json(array $body, int $status): string
    {
        http_response_code($status);
        header('Content-Type: application/json');
        return (string) json_encode($body, JSON_UNESCAPED_SLASHES);
    }
}
