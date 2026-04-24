<?php

declare(strict_types=1);

namespace AsceticSoft\RowcastSchema\Tests\Parser;

use AsceticSoft\RowcastSchema\Attribute\Column;
use AsceticSoft\RowcastSchema\Parser\PropertyTypeResolver;
use AsceticSoft\RowcastSchema\Schema\ColumnType;
use PHPUnit\Framework\TestCase;

enum PropertyTypeResolverStringEnum: string
{
    case Draft = 'draft';
    case Published = 'published';
}

enum PropertyTypeResolverIntEnum: int
{
    case Draft = 1;
    case Published = 2;
}

final class PropertyTypeResolverFixture
{
    public int $intValue;
    public ?string $nullableString = null;
    public array $payload = [];
    public \DateTimeImmutable $createdAt;
    public PropertyTypeResolverStringEnum $status = PropertyTypeResolverStringEnum::Draft;
    public PropertyTypeResolverIntEnum $priority;
    public int|string $unsupportedUnion;
    public array $arrayDefault = [];
}

final class PropertyTypeResolverUntypedFixture
{
    public $value;
}

final class PropertyTypeResolverTest extends TestCase
{
    public function testReturnsExplicitNonEnumColumnType(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'intValue');

        [$type, $databaseType, $enumValues] = $resolver->resolveType(new Column(type: ColumnType::Boolean), $property, PropertyTypeResolverFixture::class);

        self::assertSame(ColumnType::Boolean, $type);
        self::assertNull($databaseType);
        self::assertSame([], $enumValues);
    }

    public function testReturnsEnumValuesForExplicitStringBackedEnum(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'status');

        [$type, $databaseType, $enumValues] = $resolver->resolveType(new Column(type: ColumnType::Enum), $property, PropertyTypeResolverFixture::class);

        self::assertSame(ColumnType::Enum, $type);
        self::assertNull($databaseType);
        self::assertSame(['draft', 'published'], $enumValues);
    }

    public function testThrowsForExplicitEnumOnNonStringBackedEnum(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'priority');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a string-backed enum property');
        $resolver->resolveType(new Column(type: ColumnType::Enum), $property, PropertyTypeResolverFixture::class);
    }

    public function testThrowsForExplicitEnumOnNonEnumProperty(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'intValue');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a backed enum property');
        $resolver->resolveType(new Column(type: ColumnType::Enum), $property, PropertyTypeResolverFixture::class);
    }

    public function testInfersCommonTypesAndDatetime(): void
    {
        $resolver = new PropertyTypeResolver();

        self::assertSame(ColumnType::Integer, $resolver->resolveType(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'intValue'), PropertyTypeResolverFixture::class)[0]);
        self::assertSame(ColumnType::Json, $resolver->resolveType(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'payload'), PropertyTypeResolverFixture::class)[0]);
        self::assertSame(ColumnType::Datetime, $resolver->resolveType(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'createdAt'), PropertyTypeResolverFixture::class)[0]);
    }

    public function testInfersEnumsAutomatically(): void
    {
        $resolver = new PropertyTypeResolver();

        [$stringEnumType, , $enumValues] = $resolver->resolveType(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'status'), PropertyTypeResolverFixture::class);
        [$intEnumType] = $resolver->resolveType(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'priority'), PropertyTypeResolverFixture::class);

        self::assertSame(ColumnType::Enum, $stringEnumType);
        self::assertSame(['draft', 'published'], $enumValues);
        self::assertSame(ColumnType::Integer, $intEnumType);
    }

    public function testThrowsForUnsupportedUnionTypeInference(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'unsupportedUnion');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to infer column type');
        $resolver->resolveType(new Column(), $property, PropertyTypeResolverFixture::class);
    }

    public function testInfersNullableFromPropertyType(): void
    {
        $resolver = new PropertyTypeResolver();

        self::assertTrue($resolver->inferNullable(new \ReflectionProperty(PropertyTypeResolverFixture::class, 'nullableString')));
        self::assertFalse($resolver->inferNullable(new \ReflectionProperty(PropertyTypeResolverFixture::class, 'intValue')));
        self::assertFalse($resolver->inferNullable(new \ReflectionProperty(PropertyTypeResolverUntypedFixture::class, 'value')));
    }

    public function testResolveDefaultPrefersAttributeDefault(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'status');

        self::assertSame('manual', $resolver->resolveDefault(new Column(default: 'manual'), $property));
    }

    public function testResolveDefaultUsesEnumAndScalarPropertyDefaults(): void
    {
        $resolver = new PropertyTypeResolver();

        self::assertSame('draft', $resolver->resolveDefault(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'status')));
        self::assertNull($resolver->resolveDefault(new Column(), new \ReflectionProperty(PropertyTypeResolverFixture::class, 'nullableString')));
    }

    public function testResolveDefaultReturnsNullForUnsupportedArrayDefault(): void
    {
        $resolver = new PropertyTypeResolver();
        $property = new \ReflectionProperty(PropertyTypeResolverFixture::class, 'arrayDefault');

        self::assertNull($resolver->resolveDefault(new Column(), $property));
    }
}
