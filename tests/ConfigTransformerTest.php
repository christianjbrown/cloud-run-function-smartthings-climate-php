<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\GcpFunction\FunctionConfigInterface;
use ChristianBrown\GcpFunction\FunctionConfigTransformerInterface;
use ChristianBrown\SmartThingsClimate\Config;
use ChristianBrown\SmartThingsClimate\ConfigTransformer;
use ChristianBrown\SmartThingsClimate\ConfigTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(Config::class)]
#[CoversClass(ConfigTransformer::class)]
final class ConfigTransformerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testTransform(): void
    {
        $env = self::validEnv();

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $functionConfigTransformer = self::createMock(FunctionConfigTransformerInterface::class);
        $functionConfigTransformer->expects(self::once())
            ->method('transform')
            ->with($env)
            ->willReturn($functionConfig);

        $transformer = new ConfigTransformer($functionConfigTransformer);
        $actual = $transformer->transform($env);

        self::assertSame('test-client-id', $actual->getClientId());
        self::assertSame('test-client-secret', $actual->getClientSecret());
        self::assertSame('test-database-dsn', $actual->getDatabaseDsn());
        self::assertSame($functionConfig, $actual->getFunctionConfig());
        self::assertSame('test-location-id', $actual->getLocationId());
        self::assertSame('test-token-url', $actual->getTokenUrl());
    }

    /**
     * @throws Exception
     */
    #[DataProvider('invalidKeyProvider')]
    public function testTransformWithMissingValue(string $key): void
    {
        $env = self::validEnv();
        $env[$key] = null;

        $functionConfigTransformer = self::createStub(FunctionConfigTransformerInterface::class);
        $transformer = new ConfigTransformer($functionConfigTransformer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('%s not set or not a string', $key));
        $transformer->transform($env);
    }

    /**
     * @throws Exception
     */
    #[DataProvider('invalidKeyProvider')]
    public function testTransformWithNonStringValue(string $key): void
    {
        $env = self::validEnv();
        $env[$key] = 42;

        $functionConfigTransformer = self::createStub(FunctionConfigTransformerInterface::class);
        $transformer = new ConfigTransformer($functionConfigTransformer);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('%s not set or not a string', $key));
        $transformer->transform($env);
    }

    /**
     * Each required key is exercised through both guard paths: a null value
     * (empty) and a non-string value (fails the type check).
     *
     * @return iterable<string, array{0: string}>
     */
    public static function invalidKeyProvider(): iterable
    {

        yield ConfigTransformerInterface::ENV_CLIENT_ID => [ConfigTransformerInterface::ENV_CLIENT_ID];
        yield ConfigTransformerInterface::ENV_CLIENT_SECRET => [ConfigTransformerInterface::ENV_CLIENT_SECRET];
        yield ConfigTransformerInterface::ENV_DATABASE_DSN => [ConfigTransformerInterface::ENV_DATABASE_DSN];
        yield ConfigTransformerInterface::ENV_LOCATION_ID => [ConfigTransformerInterface::ENV_LOCATION_ID];
        yield ConfigTransformerInterface::ENV_TOKEN_URL => [ConfigTransformerInterface::ENV_TOKEN_URL];
    }

    /**
     * @return mixed[]
     */
    private static function validEnv(): array
    {
        return [
            ConfigTransformerInterface::ENV_CLIENT_ID => 'test-client-id',
            ConfigTransformerInterface::ENV_CLIENT_SECRET => 'test-client-secret',
            ConfigTransformerInterface::ENV_DATABASE_DSN => 'test-database-dsn',
            ConfigTransformerInterface::ENV_LOCATION_ID => 'test-location-id',
            ConfigTransformerInterface::ENV_TOKEN_URL => 'test-token-url',
        ];
    }
}
