<?php
declare(strict_types=1);

namespace Silver\Support;

final class Fake
{
    private const array FAKES = [
        'name'        => 'Max',
        'username'    => 'SilverEngine',
        'email'       => 'admin@localhost',
        'address'     => 'Silver street 16',
        'fulladdress' => 'Silver street 16, 543669 New City',
        'phonenumber' => '+00 000 000 000',
        'title'       => 'My new title placeholder',
        'text'        => 'This is sample of text placeholder for your page. Thank you to choose Silver engine framework.',
        'image'       => '<img src="System/Libs/images/silverlogo.png">',
    ];

    public static function __callStatic(string $name, array $args): string|int
    {
        $echo = $args[0] ?? false;

        if ($name === 'id' || $name === 'number') {
            $value = (string) random_int(0, 100);
        } else {
            $value = self::FAKES[$name] ?? throw new \Exception("Unknown fake: $name");
        }

        if ($echo) {
            echo $value;
        }

        return $value;
    }
}
