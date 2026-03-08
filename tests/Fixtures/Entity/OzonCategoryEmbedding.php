<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Fixtures\Entity;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Attribute\Table;

#[Table('ozon_categories')]
final class OzonCategoryEmbedding
{
    #[Column(databaseType: 'vector(1024)')]
    public string $gigachatEmbedding;

    #[Column(databaseType: 'vector(1536)')]
    public string $openaiEmbedding;
}
