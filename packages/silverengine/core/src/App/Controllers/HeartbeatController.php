<?php
declare(strict_types=1);

namespace System\App\Controllers;

use Silver\Core\Controller;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Support\Heartbeat;

/**
 * GET /heartbeat — framework self-check.
 *
 * Returns a JSON status envelope from {@see Heartbeat::run()} by default —
 * load balancers and uptime probes read it directly:
 *
 *   ok       → 200
 *   degraded → 200  (still healthy enough to serve traffic)
 *   down     → 503  (pull this instance out of rotation)
 *
 * Humans browsing in a browser get the HTML visualization (timeline graph,
 * checks grid, performance stats) at /heartbeat?view=html — or when the
 * request's Accept header prefers text/html.
 *
 * Cheap by design — runs in single-digit ms in a healthy install.
 */
final class HeartbeatController extends Controller
{
    public function __invoke(Request $request, Response $response): string
    {
        $report = (new Heartbeat())->run();
        $code = $report['status'] === 'down' ? 503 : 200;
        $response->setCode($code);
        $response->setHeader('Cache-Control', 'no-store');

        if ($this->wantsHtml($request)) {
            $response->setHeader('Content-Type', 'text/html; charset=utf-8');
            return $this->renderView($report);
        }

        $response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function wantsHtml(Request $request): bool
    {
        // Explicit ?view= override wins over Accept negotiation.
        $view = (string) $request->input('view');
        if ($view === 'html') return true;
        if ($view === 'json') return false;

        $accept = (string) ($request->headerValue('Accept') ?? '');
        // Browser-shaped Accept header: text/html shows up before
        // application/json (or application/json isn't in the list at all).
        $htmlPos = stripos($accept, 'text/html');
        $jsonPos = stripos($accept, 'application/json');
        if ($htmlPos !== false && ($jsonPos === false || $htmlPos < $jsonPos)) {
            return true;
        }
        return false;
    }

    /** @param array<string,mixed> $report */
    private function renderView(array $report): string
    {
        ob_start();
        require dirname(__DIR__) . '/Views/heartbeat.php';
        return (string) ob_get_clean();
    }
}
