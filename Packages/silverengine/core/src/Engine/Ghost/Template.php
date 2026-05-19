<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

use Silver\Core\Contracts\RenderInterface;
use Silver\Core\Route;
use Silver\Http\Session;
use Silver\Http\Request;

class Template implements RenderInterface
{
    private string $file;
    private array $data = [];
    private ?string $master = null;
    private bool $debug = false;

    public function __construct(string $file, array $data = [])
    {
        $this->file = $file;
        $this->data = $data;
    }

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function render(): string
    {
        if (!file_exists($this->file)) {
            throw new \Exception("File not found {$this->file}");
        }

        $render = file_get_contents($this->file);

        $render = $this->parseDebug($render);
        $render = $this->parseComments($render);
        $render = $this->parseIncludes($render);
        $render = $this->filterIf($render);
        $render = $this->filterForeach($render);
        $render = $this->filterFor($render);
        $render = $this->parseLang($render);
        $render = $this->parseTrans($render);
        $render = $this->parseExtends($render);
        $render = $this->parseAssets($render);
        $render = $this->parseAssetsCss($render);
        $render = $this->parseAssetsJs($render);
        $render = $this->parseUrlName($render);
        $render = $this->parseComponent($render);
        $render = $this->parseBlocks($render);
        $render = $this->parseVars($render);
        $render = $this->parseVarsSkip($render);

        if ($this->master !== null) {
            $render = $this->parseMaster($render);
        }

        foreach ($this->data as $key => $value) {
            $$key = $value;
        }

        if ($this->debug) {
            echo $render;
            exit;
        }

        try {
            ob_start();
            eval('?>' . $render);
            $render = ob_get_contents();
        } finally {
            ob_get_clean();
        }

        return $render;
    }

    private function parseDebug(string $body): string
    {
        return preg_replace_callback(
            '/#debug[\s*]*/',
            function () {
                $this->debug = true;
                return '';
            },
            $body,
        );
    }

    private function parseVars(string $body): string
    {
        // Raw output {{{ }}}
        $body = preg_replace_callback(
            '/{{{([^}]*)}}}/s',
            fn(array $m) => '<?php echo @' . trim($m[1]) . ';?>',
            $body,
        );

        // Vue/Blade skip @{{ }}
        $body = preg_replace_callback(
            '/@{{([^}]*)}}/s',
            fn(array $m) => '{@{@' . htmlentities(trim($m[1])) . '@}@}',
            $body,
        );

        // Escaped output {{ }}
        $body = preg_replace_callback(
            '/{{([^}]*)}}/s',
            fn(array $m) => '<?php echo @htmlentities(' . trim($m[1]) . '); ?>',
            $body,
        );

        return $body;
    }

    private function parseVarsSkip(string $body): string
    {
        return preg_replace_callback(
            '/{@{@([^}]*)@}@}/s',
            fn(array $m) => '{{ ' . htmlentities(trim($m[1])) . ' }}',
            $body,
        );
    }

    private function parseBlocks(string $body): string
    {
        return preg_replace_callback(
            '/#block\((.*)\)/',
            fn(array $m) => '{{{ $_block_' . trim($m[1]) . ' }}}',
            $body,
        );
    }

    private function filterIf(string $body): string
    {
        $body = preg_replace_callback('/#if.*/', fn(array $m) => '<?php ' . substr(trim($m[0]), 1) . ' { ?>', $body);
        $body = preg_replace_callback('/#elseif.*/', fn(array $m) => '<?php } ' . substr(trim($m[0]), 1) . ' { ?>', $body);
        $body = preg_replace('/#else/', '<?php } else { ?>', $body);
        $body = preg_replace('/#endif/', '<?php } ?>', $body);
        return $body;
    }

    private function filterForeach(string $body): string
    {
        $body = preg_replace_callback('/#foreach.*/', fn(array $m) => '<?php ' . substr(trim($m[0]), 1) . ' { ?>', $body);
        $body = preg_replace('/#endforeach/', '<?php } ?>', $body);
        return $body;
    }

    private function filterFor(string $body): string
    {
        $body = preg_replace_callback('/#for.*/', fn(array $m) => '<?php ' . substr(trim($m[0]), 1) . ' { ?>', $body);
        $body = preg_replace('/#endfor/', '<?php } ?>', $body);
        return $body;
    }

    protected function parseComments(string $body): string
    {
        return preg_replace('/<!--(.*)-->/', '', $body);
    }

    protected function parseMaster(string $render): string
    {
        $master = str_replace('.', '/', $this->master);
        $blocks = [];
        $currentBlock = null;
        $blockContent = '';

        foreach (explode("\n", $render) as $line) {
            if (preg_match('/#set\[(.*)\]/s', $line, $matches)) {
                $currentBlock = $matches[1];
                continue;
            }

            if (preg_match('/#end/', $line)) {
                if ($currentBlock !== null) {
                    $blocks[$currentBlock] = $blockContent;
                }
                $blockContent = '';
                $currentBlock = null;
                continue;
            }

            $blockContent .= $line . "\n";
        }

        $fullpath = ROOT . "App/Views/{$master}.ghost.php";
        if (!is_file($fullpath)) {
            $fullpath = ROOT . "App/Views/{$master}.ghost.tpl";
        }

        $ghost = new self($fullpath);

        foreach ($blocks as $key => $value) {
            $ghost->set('_block_' . $key, $value);
        }
        foreach ($this->data as $key => $value) {
            $ghost->set($key, $value);
        }

        return $ghost->render();
    }

    protected function parseAssets(string $body): string
    {
        return $this->processLines($body, "/{{ asset\('(.*)'\) }}/s", fn(string $path) => URL . '/assets/' . $path);
    }

    protected function parseAssetsCss(string $body): string
    {
        return $this->processLines($body, "/{{ css\('(.*)'\) }}/s", fn(string $path) => '<link rel="stylesheet" href="' . URL . '/assets/css/' . $path . '.css">');
    }

    protected function parseAssetsJs(string $body): string
    {
        return $this->processLines($body, "/{{ js\('(.*)'\) }}/s", fn(string $path) => '<script src="' . URL . '/assets/js/' . $path . '.js"></script>');
    }

    protected function parseLang(string $body): string
    {
        return $this->processLines($body, "/{{ lang\('(.*)'\) }}/s", fn(string $path) => $this->processLang($path));
    }

    protected function parseTrans(string $body): string
    {
        return $this->processLines($body, "/{{ trans\('(.*)'\) }}/s", fn(string $path) => $this->processLang($path));
    }

    protected function parseIncludes(string $body): string
    {
        return $this->processLines($body, "/{{ include\('(.*)'\) }}/", fn(string $alias) => $this->includeFile($alias));
    }

    protected function parseComponent(string $body): string
    {
        return $this->processLines($body, "/{{ component\('(.*)'\) }}/", fn(string $alias) => $this->includeComponent($alias));
    }

    protected function parseUrlName(string $body): string
    {
        $lines = explode("\n", $body);
        foreach ($lines as $key => $value) {
            $lines[$key] = preg_replace_callback(
                '/@routeName\(([^,)]*)(.*)\)/s',
                fn() => (new Request())->segment(1) ?? '',
                $value,
            );
        }
        return implode("\n", $lines);
    }

    protected function parseExtends(string $body): string
    {
        return preg_replace_callback(
            "/{{ extends\('(.*)'\) }}/",
            function (array $match) {
                $this->master = $match[1];
                return '';
            },
            $body,
        );
    }

    protected function processLang(string $relativePath): string
    {
        $parts = explode('.', $relativePath);
        $lang = Session::exists('lang') ? Session::get('lang') : 'en';
        $file = include ROOT . 'Storage/Lang/' . $lang . '/' . $parts[0] . '.php';
        return (string) ($file[$parts[1]] ?? '');
    }

    protected function includeFile(string $alias): string
    {
        $alias = str_replace('.', '/', $alias);
        $fullpath = ROOT . "App/Views/{$alias}.ghost.php";
        if (!is_file($fullpath)) {
            $fullpath = ROOT . "App/Views/{$alias}.ghost.tpl";
        }

        if (file_exists($fullpath)) {
            return (new self($fullpath))->render();
        }

        return '';
    }

    protected function includeComponent(string $alias): string
    {
        $alias = str_replace('.', '/', $alias);
        $fullpath = ROOT . "App/Views/components/{$alias}.ghost.php";
        if (!is_file($fullpath)) {
            $fullpath = ROOT . "App/Views/components/{$alias}.ghost.tpl";
        }

        if (file_exists($fullpath)) {
            $ghost = new self($fullpath);
            $ghost->data = $this->data;
            return $ghost->render();
        }

        return '';
    }

    protected function getRoute(string $routeName, array $vars = []): string
    {
        $route = Route::getRoute($routeName);
        return $route->url($vars);
    }

    /**
     * DRY helper: process each line of body through a regex callback.
     */
    private function processLines(string $body, string $pattern, callable $processor): string
    {
        $lines = explode("\n", $body);
        foreach ($lines as $key => $value) {
            $lines[$key] = preg_replace_callback(
                $pattern,
                fn(array $m) => $processor($m[1]),
                $value,
            );
        }
        return implode("\n", $lines);
    }
}
