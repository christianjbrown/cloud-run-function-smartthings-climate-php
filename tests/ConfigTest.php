<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps\Tests;

use ChristianBrown\CloudFunction\FunctionConfigInterface;
use ChristianBrown\GetSmartHomeTemps\Config;
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
        $config = new Config($functionConfig, 'test-api-token');
        self::assertSame($functionConfig, $config->getFunctionConfig());
        self::assertSame('test-api-token', $config->getApiToken());
    }
}
