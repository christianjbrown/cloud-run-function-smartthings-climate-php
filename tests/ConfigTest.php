<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\GcpFunction\FunctionConfigInterface;
use ChristianBrown\SmartThingsClimate\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        $functionConfig = self::createStub(FunctionConfigInterface::class);
        $config = new Config($functionConfig, 'test-client-id', 'test-client-secret', 'test-database-dsn', 'test-location-id', 'test-token-url');
        self::assertSame($functionConfig, $config->getFunctionConfig());
        self::assertSame('test-client-id', $config->getClientId());
        self::assertSame('test-client-secret', $config->getClientSecret());
        self::assertSame('test-database-dsn', $config->getDatabaseDsn());
        self::assertSame('test-location-id', $config->getLocationId());
        self::assertSame('test-token-url', $config->getTokenUrl());
    }
}
