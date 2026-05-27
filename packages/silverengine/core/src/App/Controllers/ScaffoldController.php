<?php
declare(strict_types=1);

namespace System\App\Controllers;

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

        $action = (string) ($request->input('action') ?: 'create');
        $type   = (string) ($request->input('type') ?: 'page');
        $name   = (string) $request->input('name');
        $url    = (string) $request->input('url');

        if ($name === '' && $url === '') {
            return $this->json(['ok' => false, 'error' => 'name is required'], 422);
        }

        try {
            if ($action === 'remove' || $action === 'delete') {
                // Legacy URL-based removal still supported when caller passed
                // `url` instead of `name` (e.g. from the 404 page).
                if ($type === 'page' && $name === '' && $url !== '') {
                    $scaffolder->unscaffold($url);
                } else {
                    $scaffolder->remove($type, $name);
                }
                header('Location: /');
                http_response_code(303);
                return '';
            }

            // Legacy URL-based scaffold (404 page flow).
            if ($type === 'page' && $url !== '' && $name !== '') {
                $result = $scaffolder->scaffold($url, $name);
                header('Location: ' . $result['url']);
                http_response_code(303);
                return '';
            }

            $result = $scaffolder->create($type, $name);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        // For page: jump to the new page. For class types: back home.
        header('Location: ' . ($result['url'] ?? '/'));
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
