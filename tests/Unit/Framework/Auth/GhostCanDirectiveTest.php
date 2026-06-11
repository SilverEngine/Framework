<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Engine\Ghost\Template;

final class GhostCanDirectiveTest extends TestCase
{
    private function compileCan(string $source): string
    {
        $template = new \ReflectionClass(Template::class)->newInstanceWithoutConstructor();
        $method   = new \ReflectionMethod(Template::class, 'parseCan');
        return (string) $method->invoke($template, $source);
    }

    public function testCanBlockCompilesToGateAllows(): void
    {
        $out = $this->compileCan("#can('update', \$post)EDIT#endcan");

        self::assertSame(
            "<?php if (gate()->allows('update', \$post)) { ?>EDIT<?php } ?>",
            $out,
        );
    }

    public function testCannotBlockCompilesToGateDenies(): void
    {
        $out = $this->compileCan("#cannot('update', \$post)NOPE#endcannot");

        self::assertSame(
            "<?php if (gate()->denies('update', \$post)) { ?>NOPE<?php } ?>",
            $out,
        );
    }
}
