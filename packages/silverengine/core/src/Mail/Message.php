<?php
declare(strict_types=1);

namespace Silver\Mail;

use Silver\Http\View;

/**
 * One outgoing email as a fluent value object + MIME assembler.
 *
 *   new Message()
 *       ->to('lex@example.test', 'Lex')
 *       ->subject('Welcome')
 *       ->view('emails/welcome', ['name' => 'Lex'])   // Ghost template → html body
 *       ->text('Welcome!')                             // plain alternative
 *       ->attach('/path/invoice.pdf');
 *
 * Addresses validate on entry; names and the subject are stripped of
 * CR/LF so user input can't inject headers. Bcc recipients are
 * delivered (they're in recipients()) but never serialized into the
 * MIME headers. mime() returns {headers, body}: text/plain or
 * text/html for single bodies, multipart/alternative for text+html
 * (plain part first), wrapped in multipart/mixed when attachments
 * exist (base64, chunked).
 */
final class Message
{
    /** @var list<array{0: string, 1: ?string}> */
    private array $to = [];
    /** @var list<array{0: string, 1: ?string}> */
    private array $cc = [];
    /** @var list<array{0: string, 1: ?string}> */
    private array $bcc = [];

    private string $fromAddress = '';
    private ?string $fromName   = null;
    private ?string $replyTo    = null;

    private string $subject  = '';
    private ?string $textBody = null;
    private ?string $htmlBody = null;

    /** @var list<array{name: string, mime: string, contents: string}> */
    private array $attachments = [];

    // ---------- recipients ----------

    public function to(string $address, ?string $name = null): self
    {
        $this->to[] = [self::address($address), self::sanitize($name)];
        return $this;
    }

    public function cc(string $address, ?string $name = null): self
    {
        $this->cc[] = [self::address($address), self::sanitize($name)];
        return $this;
    }

    public function bcc(string $address, ?string $name = null): self
    {
        $this->bcc[] = [self::address($address), self::sanitize($name)];
        return $this;
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->fromAddress = self::address($address);
        $this->fromName    = self::sanitize($name);
        return $this;
    }

    public function replyTo(string $address): self
    {
        $this->replyTo = self::address($address);
        return $this;
    }

    // ---------- content ----------

    public function subject(?string $subject = null): string|self
    {
        if ($subject === null) {
            return $this->subject;
        }
        $this->subject = (string) self::sanitize($subject);
        return $this;
    }

    public function text(string $body): self
    {
        $this->textBody = $body;
        return $this;
    }

    public function html(string $body): self
    {
        $this->htmlBody = $body;
        return $this;
    }

    /** Render a Ghost view (app/Views/…) as the html body. */
    public function view(string $template, array $data = []): self
    {
        return $this->html((string) View::make($template, $data)->render());
    }

    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new MailException("Cannot read attachment '{$path}'.");
        }
        return $this->attachData(
            $contents,
            $name ?? basename($path),
            $mime ?? (new \finfo(FILEINFO_MIME_TYPE)->file($path) ?: 'application/octet-stream'),
        );
    }

    public function attachData(string $contents, string $name, string $mime = 'application/octet-stream'): self
    {
        $this->attachments[] = [
            'name'     => (string) self::sanitize($name),
            'mime'     => (string) self::sanitize($mime),
            'contents' => $contents,
        ];
        return $this;
    }

    // ---------- introspection ----------

    public function hasFrom(): bool
    {
        return $this->fromAddress !== '';
    }

    /** @return list<string> every envelope recipient (to + cc + bcc) */
    public function recipients(): array
    {
        return array_map(
            static fn (array $a): string => $a[0],
            [...$this->to, ...$this->cc, ...$this->bcc],
        );
    }

    public function fromAddress(): string
    {
        return $this->fromAddress;
    }

    // ---------- MIME ----------

    /** @return array{headers: array<string, string>, body: string} */
    public function mime(): array
    {
        if ($this->to === []) {
            throw new MailException('Message has no recipients.');
        }
        if ($this->textBody === null && $this->htmlBody === null) {
            throw new MailException('Message has no body.');
        }

        $headers = [
            'MIME-Version' => '1.0',
            'From'         => $this->formatAddress($this->fromAddress, $this->fromName),
            'To'           => $this->formatList($this->to),
        ];
        if ($this->cc !== []) {
            $headers['Cc'] = $this->formatList($this->cc);
        }
        if ($this->replyTo !== null) {
            $headers['Reply-To'] = $this->replyTo;
        }

        [$contentType, $body] = $this->assembleBody();
        $headers['Content-Type'] = $contentType;

        return ['headers' => $headers, 'body' => $body];
    }

    /** @return array{0: string, 1: string} [Content-Type header value, body] */
    private function assembleBody(): array
    {
        [$innerType, $inner] = $this->alternativePart();

        if ($this->attachments === []) {
            return [$innerType, $inner];
        }

        $boundary = 'mixed_' . bin2hex(random_bytes(14));
        $parts    = [
            "--{$boundary}\r\nContent-Type: {$innerType}\r\n\r\n{$inner}",
        ];
        foreach ($this->attachments as $a) {
            $parts[] = "--{$boundary}\r\n"
                . "Content-Type: {$a['mime']}; name=\"{$a['name']}\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . "Content-Disposition: attachment; filename=\"{$a['name']}\"\r\n\r\n"
                . chunk_split(base64_encode($a['contents']), 76, "\r\n");
        }

        return [
            "multipart/mixed; boundary=\"{$boundary}\"",
            implode("\r\n", $parts) . "--{$boundary}--",
        ];
    }

    /** @return array{0: string, 1: string} the text/html (or alternative) inner part */
    private function alternativePart(): array
    {
        if ($this->textBody !== null && $this->htmlBody !== null) {
            $boundary = 'alt_' . bin2hex(random_bytes(14));
            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . $this->textBody . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                . $this->htmlBody . "\r\n"
                . "--{$boundary}--";
            return ["multipart/alternative; boundary=\"{$boundary}\"", $body];
        }
        if ($this->htmlBody !== null) {
            return ['text/html; charset=UTF-8', $this->htmlBody];
        }
        return ['text/plain; charset=UTF-8', (string) $this->textBody];
    }

    // ---------- helpers ----------

    private function formatAddress(string $address, ?string $name): string
    {
        return ($name !== null && $name !== '') ? "{$name} <{$address}>" : $address;
    }

    /** @param list<array{0: string, 1: ?string}> $list */
    private function formatList(array $list): string
    {
        return implode(', ', array_map(
            fn (array $a): string => $this->formatAddress($a[0], $a[1]),
            $list,
        ));
    }

    private static function address(string $address): string
    {
        $clean = filter_var(trim($address), FILTER_VALIDATE_EMAIL);
        if ($clean === false) {
            throw new MailException("Invalid email address '{$address}'.");
        }
        return $clean;
    }

    private static function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return trim(str_replace(["\r", "\n", "\t"], ' ', $value));
    }
}
