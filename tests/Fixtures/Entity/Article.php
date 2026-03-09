<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Fixtures\Entity;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Attribute\Table;

#[Table]
final class Article
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column]
    public bool $published = false;

    #[Column(default: true)]
    public bool $featured = false;
}
