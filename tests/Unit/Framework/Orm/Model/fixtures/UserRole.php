<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model\fixtures;

enum UserRole: string
{
    case Member = 'member';
    case Admin  = 'admin';
    case Owner  = 'owner';
}
