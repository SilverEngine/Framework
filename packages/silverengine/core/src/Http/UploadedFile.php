<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Exception\Exception;
use Silver\FileSystem\FileSystem;
use Silver\FileSystem\LocalFileSystem;

/**
 * One uploaded file from $_FILES, as a value object.
 *
 * Everything client-supplied (name, type) is exposed under client*()
 * and treated as untrusted; {@see mimeType()} / {@see extension()} /
 * {@see isImage()} inspect the actual bytes via finfo.
 *
 * `$test = true` (unit tests, fakes) skips the is_uploaded_file() SAPI
 * check and moves with rename() instead of move_uploaded_file().
 */
final readonly class UploadedFile
{
    /** Real-mime → canonical extension, also drives the `mimes:` rule. */
    private const array MIME_TO_EXT = [
        'image/jpeg'         => 'jpg',
        'image/png'          => 'png',
        'image/gif'          => 'gif',
        'image/webp'         => 'webp',
        'image/svg+xml'      => 'svg',
        'image/avif'         => 'avif',
        'image/bmp'          => 'bmp',
        'application/pdf'    => 'pdf',
        'application/zip'    => 'zip',
        'application/json'   => 'json',
        'text/plain'         => 'txt',
        'text/csv'           => 'csv',
        'text/html'          => 'html',
        'audio/mpeg'         => 'mp3',
        'video/mp4'          => 'mp4',
        'video/webm'         => 'webm',
    ];

    public function __construct(
        private string $tmpPath,
        private string $clientName,
        private string $clientType,
        private int    $size,
        private int    $error = UPLOAD_ERR_OK,
        private bool   $test = false,
    ) {}

    /**
     * Build from one $_FILES entry (the single-file shape).
     *
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $spec
     */
    public static function fromSpec(array $spec, bool $test = false): self
    {
        return new self(
            tmpPath:    (string) ($spec['tmp_name'] ?? ''),
            clientName: (string) ($spec['name'] ?? ''),
            clientType: (string) ($spec['type'] ?? ''),
            size:       (int) ($spec['size'] ?? 0),
            error:      (int) ($spec['error'] ?? UPLOAD_ERR_NO_FILE),
            test:       $test,
        );
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->tmpPath !== ''
            && is_file($this->tmpPath)
            && ($this->test || is_uploaded_file($this->tmpPath));
    }

    public function error(): int      { return $this->error; }
    public function size(): int       { return $this->size; }
    public function path(): string    { return $this->tmpPath; }

    // ---------- client-supplied (untrusted) ----------

    public function clientName(): string     { return $this->clientName; }
    public function clientMimeType(): string { return $this->clientType; }

    public function clientExtension(): string
    {
        return strtolower(pathinfo($this->clientName, PATHINFO_EXTENSION));
    }

    // ---------- content-derived (trusted) ----------

    /** Real mime type from the file bytes (finfo), not the client claim. */
    public function mimeType(): string
    {
        if ($this->tmpPath === '' || !is_file($this->tmpPath)) {
            return 'application/octet-stream';
        }
        $info = new \finfo(FILEINFO_MIME_TYPE);
        return $info->file($this->tmpPath) ?: 'application/octet-stream';
    }

    /** Extension guessed from the real mime; client extension fallback. */
    public function extension(): string
    {
        return self::MIME_TO_EXT[$this->mimeType()] ?? $this->clientExtension();
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType(), 'image/');
    }

    public function content(): string
    {
        return (string) file_get_contents($this->tmpPath);
    }

    // ---------- persistence ----------

    /**
     * Move the upload into $directory and return the final path. With
     * no $name, a random 32-hex name with the content-derived
     * extension is used (never the client name — no traversal, no
     * double-extension tricks).
     */
    public function store(string $directory, ?string $name = null, ?FileSystem $fs = null): string
    {
        if (!$this->isValid()) {
            throw new Exception(
                "Cannot store invalid upload '{$this->clientName}' (error {$this->error}).",
            );
        }

        $fs ??= new LocalFileSystem();
        $directory = rtrim($directory, '/');
        if (!$fs->mkdir($directory)) {
            throw new Exception("Cannot create upload directory '{$directory}'.");
        }

        $name ??= bin2hex(random_bytes(16)) . '.' . $this->extension();
        $dest   = $directory . '/' . basename($name);

        $moved = $this->test
            ? $fs->move($this->tmpPath, $dest)
            : move_uploaded_file($this->tmpPath, $dest);
        if (!$moved) {
            throw new Exception("Failed to move upload to '{$dest}'.");
        }

        return $dest;
    }
}
