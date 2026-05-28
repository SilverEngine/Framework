<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

use Silver\Core\Contracts\RenderInterface;
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

        // Always track the current source in any enclosing compile frame so
        // a parent's cache invalidates when this template changes.
        Compiler::track($this->file);

        $cacheFile = Compiler::pathFor($this->file);

        // Fast path: cache is fresh — skip the regex pipeline entirely.
        // freshDeps returns the tracked deps map on a cache hit (null on
        // miss) so we can bubble them into the parent frame in one pass.
        $cachedDeps = Compiler::freshDeps($cacheFile, $this->file);
        if ($cachedDeps !== null) {
            Compiler::trackMany($cachedDeps);
            return $this->execute($cacheFile);
        }

        // Compile and write.
        Compiler::startFrame();
        try {
            Compiler::track($this->file);
            $compiled = $this->compile();
            $deps = Compiler::endFrame();
            Compiler::write($cacheFile, $this->file, $deps, $compiled);
        } catch (\Throwable $e) {
            Compiler::endFrame();
            throw $e;
        }

        if ($this->debug) {
            echo file_get_contents($cacheFile);
            exit;
        }

        return $this->execute($cacheFile);
    }

    /**
     * Run the compile pipeline against the source file and return the
     * compiled PHP-template string (without the cache file header).
     */
    private function compile(): string
    {
        $render = (string) file_get_contents($this->file);

        $render = $this->parseDebug($render);
        $render = $this->parseComments($render);
        $render = $this->parseIncludes($render);
        $render = $this->filterControl($render, 'if');
        $render = $this->filterControl($render, 'foreach');
        $render = $this->filterControl($render, 'for');
        $render = $this->parseLang($render);
        $render = $this->parseTrans($render);
        $render = $this->parseExtends($render);
        $render = $this->parseAssets($render);
        $render = $this->parseAssetsCss($render);
        $render = $this->parseAssetsJs($render);
        $render = $this->parseVite($render);
        $render = $this->parseViteCss($render);
        $render = $this->parseWisp($render);
        $render = $this->parseCsrf($render);
        $render = $this->parseUrlName($render);
        $render = $this->parseComponent($render);
        $render = $this->parseBlocks($render);
        $render = $this->parseVars($render);
        $render = $this->parseVarsSkip($render);

        if ($this->master !== null) {
            $render = $this->parseMaster($render);
        }

        return $render;
    }

    /**
     * Include the cached compiled file in an output buffer with the
     * template data exposed as local vars. Opcache picks up the file
     * the same as any other PHP source — no eval() at request time.
     */
    private function execute(string $cacheFile): string
    {
        ob_start();
        try {
            extract($this->data, EXTR_SKIP);
            include $cacheFile;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
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
        // Raw output, Laravel-style: {!! $html !!} — emits the expression
        // verbatim, caller is responsible for safety. Run BEFORE {{{ }}}
        // and {{ }} so the `!!` markers can't be misread as braces.
        $body = preg_replace_callback(
            '/{!!\s*(.+?)\s*!!}/s',
            fn(array $m) => '<?php echo ' . trim($m[1]) . ';?>',
            $body,
        );

        // Raw output, Mustache-style: {{{ $html }}} — identical semantics
        // to {!! !!}, kept for backwards compatibility.
        $body = preg_replace_callback(
            '/{{{([^}]*)}}}/s',
            fn(array $m) => '<?php echo ' . trim($m[1]) . ';?>',
            $body,
        );

        // Vue/Blade skip @{{ }} — escape the expression text into a marker so
        // subsequent passes ignore it; parseVarsSkip restores it verbatim.
        $body = preg_replace_callback(
            '/@{{([^}]*)}}/s',
            fn(array $m) => '{@{@' . htmlspecialchars(trim($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '@}@}',
            $body,
        );

        // Escaped output {{ }} — htmlspecialchars with ENT_QUOTES is the
        // OWASP-correct escape for HTML body/attribute context. SUBSTITUTE
        // keeps invalid UTF-8 sequences from blowing up the output.
        $body = preg_replace_callback(
            '/{{([^}]*)}}/s',
            fn(array $m) => '<?php echo htmlspecialchars((string)(' . trim($m[1]) . ' ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>',
            $body,
        );

        return $body;
    }

    private function parseVarsSkip(string $body): string
    {
        return preg_replace_callback(
            '/{@{@([^}]*)@}@}/s',
            fn(array $m) => '{{ ' . htmlspecialchars_decode(trim($m[1]), ENT_QUOTES) . ' }}',
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

    /**
     * Compile a PHP control block (#if / #foreach / #for) into raw PHP.
     * Each tag rewrites the opener to `<?php ${tag} { ?>` and the
     * matching `#end<tag>` to `<?php } ?>`. #if additionally supports
     * #elseif and #else.
     */
    private function filterControl(string $body, string $tag): string
    {
        $body = preg_replace_callback(
            '/#' . $tag . '.*/',
            fn (array $m) => '<?php ' . substr(trim($m[0]), 1) . ' { ?>',
            $body,
        );
        if ($tag === 'if') {
            $body = preg_replace_callback(
                '/#elseif.*/',
                fn (array $m) => '<?php } ' . substr(trim($m[0]), 1) . ' { ?>',
                $body,
            );
            $body = preg_replace('/#else/', '<?php } else { ?>', $body);
        }
        return preg_replace('/#end' . $tag . '/', '<?php } ?>', $body);
    }

    protected function parseComments(string $body): string
    {
        return preg_replace('/<!--(.*)-->/', '', $body);
    }

    protected function parseMaster(string $render): string
    {
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

        $fullpath = self::resolveView($this->master);
        if ($fullpath === null) {
            return $render;
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

    /**
     * {{ vite() }} -> a runtime call to Vite::tags(). Deferred (rather than
     * inlined) so a fresh `npm run build` is picked up without invalidating
     * the compiled template cache.
     */
    protected function parseVite(string $body): string
    {
        return preg_replace(
            '/{{ vite\(\) }}/',
            '<?php echo \\Silver\\Engine\\Ghost\\Vite::tags(); ?>',
            $body,
        );
    }

    /** {{ viteCss() }} -> runtime Vite::cssTags(). Same rationale as parseVite. */
    protected function parseViteCss(string $body): string
    {
        return preg_replace(
            '/{{ viteCss\(\) }}/',
            '<?php echo \\Silver\\Engine\\Ghost\\Vite::cssTags(); ?>',
            $body,
        );
    }

    /**
     * {{ wisp() }} -> runtime emission of the Inertia root element. The
     * page object varies per request, so the call is deferred and reads
     * the `_wisp_page` template data at execute time.
     */
    protected function parseWisp(string $body): string
    {
        return preg_replace(
            '/{{ wisp\(\) }}/',
            '<?php echo \\Silver\\Engine\\Ghost\\Wisp::el($_wisp_page ?? []); ?>',
            $body,
        );
    }

    /**
     * {{ csrf() }} -> hidden <input> with the current session CSRF
     * token. Token comes from the framework's TokenStore at render
     * time, so cache reuse is safe — the resolved value is always
     * the request's current token, not whatever was baked at compile.
     */
    protected function parseCsrf(string $body): string
    {
        return preg_replace(
            '/{{ csrf\(\) }}/',
            '<?php echo csrf_field(); ?>',
            $body,
        );
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
        $file = include ROOT . 'storage/Lang/' . $lang . '/' . $parts[0] . '.php';
        return (string) ($file[$parts[1]] ?? '');
    }

    protected function includeFile(string $alias): string
    {
        $fullpath = self::resolveView($alias);
        return $fullpath !== null ? (new self($fullpath))->render() : '';
    }

    protected function includeComponent(string $alias): string
    {
        $fullpath = self::resolveView($alias, 'components/');
        if ($fullpath === null) {
            return '';
        }
        $ghost = new self($fullpath);
        $ghost->data = $this->data;
        return $ghost->render();
    }

    /**
     * Locate a Ghost view by dotted alias under `app/Views/[$subdir]`,
     * trying `.ghost.php` first then `.ghost.tpl`. Returns the resolved
     * absolute path or null if neither exists.
     *
     * Centralises the dotted-alias → file lookup that previously lived
     * inline in {@see parseMaster()}, {@see includeFile()} and
     * {@see includeComponent()}.
     */
    private static function resolveView(string $alias, string $subdir = ''): ?string
    {
        $relative = str_replace('.', '/', $alias);
        foreach (['.ghost.php', '.ghost.tpl'] as $ext) {
            $path = ROOT . 'app/Views/' . $subdir . $relative . $ext;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
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
