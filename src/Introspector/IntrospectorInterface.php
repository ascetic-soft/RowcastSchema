<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Introspector;

use AsceticSoft\RowcastSchema\Schema\Schema;

interface IntrospectorInterface
{
    public function introspect(\PDO $pdo): Schema;
}
