<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Request;
use Silver\Http\UploadedFile;

final class UploadedFileTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            if (is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                foreach (glob($path . '/*') ?: [] as $f) { @unlink($f); }
                @rmdir($path);
            }
        }
        $_FILES = [];
    }

    private function tmpFile(string $contents, string $clientName, int $error = UPLOAD_ERR_OK): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sup');
        file_put_contents($path, $contents);
        $this->cleanup[] = $path;

        return new UploadedFile(
            tmpPath:    $path,
            clientName: $clientName,
            clientType: 'application/octet-stream',
            size:       strlen($contents),
            error:      $error,
            test:       true,
        );
    }

    private function gifFile(string $clientName = 'pixel.gif'): UploadedFile
    {
        // GIF87a signature is enough for finfo to say image/gif.
        return $this->tmpFile("GIF87a\x01\x00\x01\x00", $clientName);
    }

    // ---------- value object ----------

    public function testBasicAccessors(): void
    {
        $f = $this->tmpFile('hello', 'notes.txt');

        self::assertTrue($f->isValid());
        self::assertSame('notes.txt', $f->clientName());
        self::assertSame('txt', $f->clientExtension());
        self::assertSame(5, $f->size());
        self::assertSame('hello', $f->content());
    }

    public function testErrorCodeMakesItInvalid(): void
    {
        $f = $this->tmpFile('x', 'a.txt', UPLOAD_ERR_INI_SIZE);

        self::assertFalse($f->isValid());
        self::assertSame(UPLOAD_ERR_INI_SIZE, $f->error());
    }

    public function testRealMimeTypeComesFromContentNotClient(): void
    {
        // Client claims octet-stream and a .txt name — content is a GIF.
        $f = $this->gifFile('disguised.txt');

        self::assertSame('image/gif', $f->mimeType());
        self::assertTrue($f->isImage());
        self::assertSame('gif', $f->extension());
    }

    public function testTextFileIsNotAnImage(): void
    {
        $f = $this->tmpFile('just words', 'notes.txt');

        self::assertFalse($f->isImage());
    }

    public function testFromSpecBuildsFromFilesEntry(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sup');
        file_put_contents($path, 'spec');
        $this->cleanup[] = $path;

        $f = UploadedFile::fromSpec([
            'name'     => 'doc.pdf',
            'type'     => 'application/pdf',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 4,
        ], test: true);

        self::assertSame('doc.pdf', $f->clientName());
        self::assertSame(4, $f->size());
        self::assertSame('application/pdf', $f->clientMimeType());
    }

    // ---------- store ----------

    public function testStoreMovesFileIntoDirectoryWithGivenName(): void
    {
        $dir = sys_get_temp_dir() . '/sup_store_' . getmypid();
        $this->cleanup[] = $dir;

        $f    = $this->tmpFile('payload', 'orig.txt');
        $dest = $f->store($dir, 'saved.txt');

        self::assertSame($dir . '/saved.txt', $dest);
        self::assertFileExists($dest);
        self::assertSame('payload', file_get_contents($dest));
    }

    public function testStoreGeneratesRandomNameWithRealExtension(): void
    {
        $dir = sys_get_temp_dir() . '/sup_store2_' . getmypid();
        $this->cleanup[] = $dir;

        $dest = $this->gifFile()->store($dir);

        self::assertFileExists($dest);
        self::assertStringEndsWith('.gif', $dest);
        self::assertMatchesRegularExpression('#/[0-9a-f]{32}\.gif$#', $dest);
    }

    public function testStoreRefusesInvalidUpload(): void
    {
        $f = $this->tmpFile('x', 'a.txt', UPLOAD_ERR_PARTIAL);

        $this->expectException(\Throwable::class);
        $f->store(sys_get_temp_dir());
    }

    // ---------- Request integration ----------

    public function testRequestFileReadsFromFilesSuperglobal(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sup');
        file_put_contents($path, 'avatar-bytes');
        $this->cleanup[] = $path;

        $_FILES['avatar'] = [
            'name'     => 'me.png',
            'type'     => 'image/png',
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => 12,
        ];

        $req  = new Request();
        $file = $req->file('avatar');

        self::assertInstanceOf(UploadedFile::class, $file);
        self::assertSame('me.png', $file->clientName());
    }

    public function testRequestFileReturnsNullWhenAbsent(): void
    {
        self::assertNull(new Request()->file('nope'));
    }

    public function testMergeFilesOverlayWinsAndHasFileChecksValidity(): void
    {
        $req = new Request();
        self::assertFalse($req->hasFile('avatar'));

        $req->mergeFiles(['avatar' => $this->gifFile()]);

        self::assertTrue($req->hasFile('avatar'));
        self::assertSame('pixel.gif', $req->file('avatar')->clientName());

        $req->mergeFiles(['broken' => $this->tmpFile('x', 'b.txt', UPLOAD_ERR_NO_FILE)]);
        self::assertFalse($req->hasFile('broken'));
    }
}
