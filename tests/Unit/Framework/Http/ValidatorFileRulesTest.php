<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\UploadedFile;
use Silver\Http\Validator;

final class ValidatorFileRulesTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $p) {
            @unlink($p);
        }
    }

    private function upload(string $contents, string $clientName, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'svf');
        file_put_contents($path, $contents);
        $this->cleanup[] = $path;

        return new UploadedFile($path, $clientName, 'application/octet-stream', strlen($contents), $error, test: true);
    }

    private function gif(): UploadedFile
    {
        return $this->upload("GIF87a\x01\x00\x01\x00", 'pic.gif');
    }

    public function testFileRulePassesForValidUpload(): void
    {
        $result = new Validator()->check(['doc' => $this->upload('x', 'a.txt')], ['doc' => 'required|file']);

        self::assertTrue($result->passes());
    }

    public function testFileRuleFailsForNonUploadAndInvalidUpload(): void
    {
        $v = new Validator();

        self::assertFalse($v->check(['doc' => 'a string'], ['doc' => 'file'])->passes());
        self::assertFalse(
            $v->check(['doc' => $this->upload('x', 'a.txt', UPLOAD_ERR_PARTIAL)], ['doc' => 'file'])->passes(),
        );
    }

    public function testImageRuleChecksRealContent(): void
    {
        $v = new Validator();

        self::assertTrue($v->check(['pic' => $this->gif()], ['pic' => 'image'])->passes());
        // .gif name, text content — must fail.
        self::assertFalse(
            $v->check(['pic' => $this->upload('plain text', 'fake.gif')], ['pic' => 'image'])->passes(),
        );
    }

    public function testMimesRuleMatchesRealMimeAgainstExtensions(): void
    {
        $v = new Validator();

        self::assertTrue($v->check(['f' => $this->gif()], ['f' => 'mimes:gif,png'])->passes());
        self::assertFalse($v->check(['f' => $this->gif()], ['f' => 'mimes:pdf,txt'])->passes());
    }

    public function testMaxAndMinMeasureFileSizeInKilobytes(): void
    {
        $v   = new Validator();
        $big = $this->upload(str_repeat('a', 3072), 'big.txt'); // 3 KB

        self::assertFalse($v->check(['f' => $big], ['f' => 'max:2'])->passes());
        self::assertTrue($v->check(['f' => $big], ['f' => 'max:4'])->passes());
        self::assertTrue($v->check(['f' => $big], ['f' => 'min:2'])->passes());
        self::assertFalse($v->check(['f' => $big], ['f' => 'min:4'])->passes());
    }

    public function testStringMaxStillCountsCharacters(): void
    {
        $result = new Validator()->check(['name' => 'abcdef'], ['name' => 'max:5']);

        self::assertFalse($result->passes());
    }

    public function testNullableFileFieldSkipsWhenAbsent(): void
    {
        $result = new Validator()->check([], ['doc' => 'nullable|file|max:100']);

        self::assertTrue($result->passes());
    }
}
