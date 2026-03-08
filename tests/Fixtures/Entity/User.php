<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Fixtures\Entity;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Attribute\Index;
use AsceticSoft\RowcastSchema\Attribute\Table;

#[Table]
#[Index('idx_users_email', columns: ['email'], unique: true)]
final class User
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 100)]
    public string $email;

    #[Column]
    public ?string $name;

    #[Column]
    public UserStatus $status;

    #[Column]
    public Priority $priority;
}
