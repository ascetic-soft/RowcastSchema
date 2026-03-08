<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Fixtures\Entity;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Attribute\Table;

#[Table('invalid_types')]
final class InvalidTypeEntity
{
    #[Column]
    public mixed $payload;
}
