<?php

declare(strict_types=1);

namespace App\Controllers;

use Silver\Core\Controller;

final class ApiController extends Controller
{
    public function __invoke(): string
    {
        header('Content-Type: application/json');
        return (string) json_encode(['message' => 'Welcome to the api']);
    }
}
