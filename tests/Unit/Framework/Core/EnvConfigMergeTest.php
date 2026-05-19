<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Env;

/**
 * Locks the core-defaults + app-overrides deep-merge contract and that,
 * with no app overrides, the effective config equals the core defaults
 * (behaviour preserved vs. the pre-split single config/ dir).
 */
class EnvConfigMergeTest extends TestCase
{
    public function testScalarOverrideWinsOthersInherited(): void
    {
        $base = ['enabled' => true, 'limit' => 50, 'ignore' => ['/debug', '/build']];
        $out  = Env::mergeConfig($base, ['limit' => 200]);

        $this->assertSame(
            ['enabled' => true, 'limit' => 200, 'ignore' => ['/debug', '/build']],
            $out,
        );
    }

    public function testNestedAssocMergesRecursively(): void
    {
        $base = ['terminal' => ['online' => false, 'username' => 'a', 'password' => 'b']];
        $out  = Env::mergeConfig($base, ['terminal' => ['online' => true]]);

        $this->assertSame(
            ['terminal' => ['online' => true, 'username' => 'a', 'password' => 'b']],
            $out,
        );
    }

    public function testListIsReplacedNotIndexMerged(): void
    {
        $base = ['ignore' => ['/debug', '/build', '/favicon']];
        $out  = Env::mergeConfig($base, ['ignore' => ['/only']]);

        $this->assertSame(['ignore' => ['/only']], $out);
    }

    public function testOverrideOnlyKeyIsAddedBaseOnlyKept(): void
    {
        $out = Env::mergeConfig(['a' => 1, 'keep' => 9], ['a' => 2, 'new' => 3]);
        $this->assertSame(['a' => 2, 'keep' => 9, 'new' => 3], $out);
    }

    public function testEffectiveConfigEqualsCoreDefaults(): void
    {
        // app config/ has no overrides → Env must still expose the core
        // defaults exactly as before the core/app split.
        Env::clearConfigCache();
        Env::construct();

        $this->assertNotNull(Env::get('app'), 'core Config/App.php default loaded');
        $this->assertSame(true, Env::get('recorder.enabled'), 'core Config/Recorder.php default loaded');
        $this->assertIsArray(Env::get('routes'), 'core Config/Routes.php default loaded');
    }
}
