<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Fixtures\EntityValid;

enum UserStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
}
