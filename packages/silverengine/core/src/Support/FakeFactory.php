<?php
declare(strict_types=1);

namespace Silver\Support;

use Silver\Database\DB;

class FakeFactory
{
    private const array FIRST_NAMES = [
        'Christopher', 'Ryan', 'Ethan', 'John', 'Zoey', 'Sarah',
        'Michelle', 'Samantha', 'Stive', 'Arthur', 'George', 'Max',
        'Graham', 'Peter', 'Michel', 'Richard', 'Glan', 'John', 'Mike',
    ];

    private const array LAST_NAMES = [
        'Walker', 'Thompson', 'Anderson', 'Johnson', 'Tremblay',
        'Peltier', 'Cunningham', 'Simpson', 'Mercado', 'Sellers',
        'Smith', 'Bloom', 'Gaddis', 'Miller', 'Racham', 'Gates', 'Jobs',
    ];

    private const array DOMAINS = ['freshc', 'local', 'localhost', 'develop', 'bingox', 'fmd', 'atr32'];
    private const array DOMAIN_EXTS = ['com', 'net', 'io', 'org', 'me', 'co.uk', 'si', 'us'];

    public function users(int $num = 1, string $table = 'users'): void
    {
        for ($i = 0; $i < $num; $i++) {
            DB::insert($table, [
                'username' => $this->fullname(),
                'email'    => $this->email(),
                'password' => $this->password(),
            ]);
        }
    }

    public function firstname(): string
    {
        return self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
    }

    public function lastname(): string
    {
        return self::LAST_NAMES[array_rand(self::LAST_NAMES)];
    }

    public function fullname(): string
    {
        return $this->firstname() . ' ' . $this->lastname();
    }

    public function domainGen(): string
    {
        return self::DOMAINS[array_rand(self::DOMAINS)] . '.' . self::DOMAIN_EXTS[array_rand(self::DOMAIN_EXTS)];
    }

    public function email(): string
    {
        return $this->firstname() . '.' . $this->lastname() . '@' . $this->domainGen();
    }

    public function password(): string
    {
        return md5($this->firstname());
    }

    public function number(): string
    {
        return self::randomDigits(3) . '-' . self::randomDigits(3) . '-' . self::randomDigits(3);
    }

    private static function randomDigits(int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= (string) random_int(0, 9);
        }
        return $result;
    }
}
