<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests\Database\Entity;

use ChristianBrown\KeyValueStore\AbstractDatabaseKeyValueStoreEntity;
use ChristianBrown\SmartThingsClimate\Database\Entity\RefreshToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefreshToken::class)]
final class RefreshTokenTest extends TestCase
{
    public function test(): void
    {
        $refreshToken = new RefreshToken();
        self::assertInstanceOf(AbstractDatabaseKeyValueStoreEntity::class, $refreshToken);
    }
}
