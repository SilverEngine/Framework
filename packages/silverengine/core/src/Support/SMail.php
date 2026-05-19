<?php
declare(strict_types=1);

namespace Silver\Support;

use Silver\Core\Env;

final class SMail
{
    private string $to = '';
    private string $from = '';
    private string $fromName = '';
    private string $subject = '';
    private string $body = '';
    private bool $disabled = false;

    public function __construct(string $to = '', string $subject = '', string $body = '')
    {
        if ($to !== '') { $this->to($to); }
        if ($subject !== '') { $this->subject($subject); }
        if ($body !== '') { $this->body($body); }

        $mail = Env::get('mail');
        if ($mail) {
            $this->from = $mail->email ?? '';
            $this->fromName = $mail->name ?? '';
            $this->disabled = (bool) ($mail->disabled ?? false);
        }
    }

    public function to(string $to): static
    {
        $this->to = $to;
        return $this;
    }

    public function from(string $from): static
    {
        $this->from = $from;
        return $this;
    }

    public function fromName(string $name): static
    {
        $this->fromName = $name;
        return $this;
    }

    public function subject(string $s): static
    {
        $this->subject = $s;
        return $this;
    }

    public function body(string $c): static
    {
        $this->body = $c;
        return $this;
    }

    private function filterName(string $name): string
    {
        return trim(strtr($name, ["\r" => '', "\n" => '', "\t" => '', '"' => "'", '<' => '[', '>' => ']']));
    }

    public function send(): bool
    {
        if ($this->from === '') { throw new \Exception("Can't send email. From field is empty."); }
        if ($this->to === '') { throw new \Exception("Can't send email. To field is empty."); }
        if ($this->subject === '') { throw new \Exception("Can't send email. Subject is empty."); }
        if ($this->body === '') { throw new \Exception("Can't send email. Body is empty."); }

        $this->from = filter_var($this->from, FILTER_SANITIZE_EMAIL);
        $this->to = filter_var($this->to, FILTER_SANITIZE_EMAIL);
        $this->fromName = $this->filterName($this->fromName);

        if (filter_var($this->to, FILTER_VALIDATE_EMAIL) === false
            || filter_var($this->from, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new \Exception("Invalid To ({$this->to}) or From ({$this->from}) email.");
        }

        $email = $this->fromName ? "{$this->fromName} <{$this->from}>" : $this->from;

        $headers = "From: $email\r\nX-Mailer: PHP/SMailer\r\n";

        if ($this->disabled) {
            return false;
        }

        return mail($this->to, $this->subject, $this->body, $headers);
    }
}
