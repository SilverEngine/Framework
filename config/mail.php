<?php
declare(strict_types=1);

return [
    'service' => filter_var(env('MAIL_SERVICE', false), FILTER_VALIDATE_BOOLEAN),
    'email'   => env('MAIL_FROM_ADDRESS', ''),
    'name'    => env('MAIL_FROM_NAME', ''),
];
