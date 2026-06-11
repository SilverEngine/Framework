<?php
declare(strict_types=1);

/*
 * Mail v2 — consumed by Silver\Mail\Mailer (container singleton,
 * `mailer()` helper). App config/mail.php deep-merges over this.
 *
 * Drivers:
 *   log      — append every message to one log file (safe dev default)
 *   file     — one .eml file per message in a directory
 *   sendmail — PHP mail()
 *   smtp     — raw-socket SMTP ('' | 'tls' (STARTTLS) | 'ssl' encryption)
 *   array    — in-memory (tests; assert via ArrayDriver::sent())
 *
 * The legacy SMail keys ('service'/'email'/'name') are kept below so
 * existing SMail call sites keep working until they migrate.
 */
return [
    'driver' => env('MAIL_DRIVER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', ''),
        'name'    => env('MAIL_FROM_NAME', ''),
    ],

    'smtp' => [
        'host'       => env('MAIL_HOST', 'localhost'),
        'port'       => (int) env('MAIL_PORT', 587),
        'username'   => env('MAIL_USERNAME', ''),
        'password'   => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'timeout'    => 10,
    ],

    // Overrides for the log/file drivers (defaults live under storage/).
    // 'log_path'  => '/abs/path/mail.log',
    // 'file_path' => '/abs/path/mail-dir',

    // Legacy (SMail) — slated for removal with SMail itself.
    'service' => filter_var(env('MAIL_SERVICE', false), FILTER_VALIDATE_BOOLEAN),
    'email'   => env('MAIL_FROM_ADDRESS', ''),
    'name'    => env('MAIL_FROM_NAME', ''),
];
