<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Console;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Silver\Console\Contracts\Command;
use Silver\Console\Input;
use Silver\Console\Output;
use Silver\Console\Registry;
use Silver\Core\Env;

final class GreetCommandStub implements Command
{
    public function description(): string
    {
        return 'Greets';
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('hello ' . $input->argument(0, 'world'));
        return $input->hasFlag('fail') ? 2 : 0;
    }
}

final class ConsoleTest extends TestCase
{
    private mixed $savedEnvData;

    protected function setUp(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $this->savedEnvData = $prop->getValue();
    }

    protected function tearDown(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $prop->setValue(null, $this->savedEnvData);
    }

    private function setCommandsConfig(array $map): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $prop->setValue(null, json_decode(json_encode(['commands' => $map])));
    }

    /** @return array{0: Output, 1: resource, 2: resource} */
    private function capturedOutput(): array
    {
        $out = fopen('php://memory', 'r+');
        $err = fopen('php://memory', 'r+');
        return [new Output($out, $err), $out, $err];
    }

    private function streamContents($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    // ---- Input ------------------------------------------------------

    public function testInputSeparatesCommandArgumentsAndFlags(): void
    {
        $in = new Input(['silver', 'db:table', 'users', '--connection=warehouse', '--pretend']);

        $this->assertSame('db:table', $in->command());
        $this->assertSame(['users'], $in->arguments());
        $this->assertSame('users', $in->argument(0));
        $this->assertSame('fallback', $in->argument(1, 'fallback'));
        $this->assertSame('warehouse', $in->flag('connection'));
        $this->assertTrue($in->flag('pretend'));
        $this->assertTrue($in->hasFlag('pretend'));
        $this->assertFalse($in->hasFlag('missing'));
        $this->assertNull($in->flag('missing'));
    }

    public function testInputWithNoArgs(): void
    {
        $in = new Input(['silver']);

        $this->assertSame('', $in->command());
        $this->assertSame([], $in->arguments());
        $this->assertSame([], $in->flags());
    }

    // ---- Output -----------------------------------------------------

    public function testOutputRoutesErrorsToStderr(): void
    {
        [$output, $out, $err] = $this->capturedOutput();

        $output->line('plain');
        $output->error('broken');

        $this->assertSame("plain\n", $this->streamContents($out));
        $this->assertSame("ERROR: broken\n", $this->streamContents($err));
    }

    public function testOutputTablePadsColumns(): void
    {
        [$output, $out] = $this->capturedOutput();

        $output->table(['NAME', 'TYPE'], [['id', 'integer'], ['email', 'text']]);

        $lines = explode("\n", trim($this->streamContents($out)));
        $this->assertSame('NAME   TYPE', $lines[0]);
        $this->assertSame('-----  -------', $lines[1]);
        $this->assertSame('id     integer', $lines[2]);
        $this->assertSame('email  text', $lines[3]);
    }

    // ---- Registry ---------------------------------------------------

    public function testRegistryFindsConfiguredCommandAndBuiltins(): void
    {
        $this->setCommandsConfig(['app:greet' => GreetCommandStub::class]);
        $registry = new Registry();

        $this->assertSame(GreetCommandStub::class, $registry->find('app:greet'));
        $this->assertNotNull($registry->find('route:list'));
        $this->assertNotNull($registry->find('db:show'));
        $this->assertNotNull($registry->find('db:table'));
        $this->assertNull($registry->find('nope:nope'));
    }

    public function testAppEntryOverridesBuiltin(): void
    {
        $this->setCommandsConfig(['route:list' => GreetCommandStub::class]);

        $this->assertSame(GreetCommandStub::class, (new Registry())->find('route:list'));
    }

    public function testRegistryRunsCommandWithInputAndReturnsExitCode(): void
    {
        $this->setCommandsConfig(['app:greet' => GreetCommandStub::class]);
        $registry = new Registry();

        [$output, $out] = $this->capturedOutput();
        $code = $registry->run('app:greet', new Input(['silver', 'app:greet', 'Lex']), $output);

        $this->assertSame(0, $code);
        $this->assertSame("hello Lex\n", $this->streamContents($out));

        [$output2] = $this->capturedOutput();
        $code = $registry->run('app:greet', new Input(['silver', 'app:greet', '--fail']), $output2);
        $this->assertSame(2, $code);
    }

    public function testRegistryRunUnknownCommandErrorsWithExitCodeOne(): void
    {
        $this->setCommandsConfig([]);
        $registry = new Registry();

        [$output, , $err] = $this->capturedOutput();
        $code = $registry->run('ghost:cmd', new Input(['silver', 'ghost:cmd']), $output);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Command not found: ghost:cmd', $this->streamContents($err));
    }
}
