<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Fixtures\Entity;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Attribute\ForeignKey;
use AsceticSoft\RowcastSchema\Attribute\Table;

#[Table('blog_posts')]
final class Post
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column]
    #[ForeignKey('fk_posts_user', referenceTable: 'users', referenceColumns: ['id'], onDelete: 'CASCADE')]
    public int $userId;
}
