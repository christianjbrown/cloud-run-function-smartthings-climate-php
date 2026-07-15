<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\CloudFunction\FunctionConfigInterface;
use ChristianBrown\CloudFunction\FunctionConfigTransformerInterface;
use ChristianBrown\SmartThingsClimate\Config;
use ChristianBrown\SmartThingsClimate\ConfigTransformer;
use ChristianBrown\SmartThingsClimate\ConfigTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Config::class)]
#[CoversClass(ConfigTransformer::class)]
final class ConfigTransformerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testTransform(): void
    {
        $env = [
            ConfigTransformerInterface::ENV_API_TOKEN => 'test-api-token',
            ConfigTransformerInterface::ENV_LOCATION_ID => 'test-location-id',
        ];

        $functionConfig = self::createStub(FunctionConfigInterface::class);

        $functionConfigTransformer = self::createMock(FunctionConfigTransformerInterface::class);
        $functionConfigTransformer->expects(self::once())
            ->method('transform')
            ->with($env)
            ->willReturn($functionConfig);

        $transformer = new ConfigTransformer($functionConfigTransformer);
        $actual = $transformer->transform($env);

        self::assertSame('test-api-token', $actual->getApiToken());
        self::assertSame($functionConfig, $actual->getFunctionConfig());
        self::assertSame('test-location-id', $actual->getLocationId());
    }

    /**
     * @param mixed[] $env
     *
     * @throws Exception
     */
    #[TestWith([[]])]
    #[TestWith([[ConfigTransformerInterface::ENV_API_TOKEN => null]])]
    #[TestWith([[ConfigTransformerInterface::ENV_API_TOKEN => 42]])]
    public function testTransformWithMissingApiKey(array $env): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('%s not set or not a string', ConfigTransformerInterface::ENV_API_TOKEN));

        $functionConfigTransformer = self::createStub(FunctionConfigTransformerInterface::class);

        $transformer = new ConfigTransformer($functionConfigTransformer);
        $transformer->transform($env);
    }

    /**
     * @param mixed[] $env
     *
     * @throws Exception
     */
    #[TestWith([[ConfigTransformerInterface::ENV_API_TOKEN => 'test-api-token']])]
    #[TestWith([[ConfigTransformerInterface::ENV_API_TOKEN => 'test-api-token', ConfigTransformerInterface::ENV_LOCATION_ID => null]])]
    #[TestWith([[ConfigTransformerInterface::ENV_API_TOKEN => 'test-api-token', ConfigTransformerInterface::ENV_LOCATION_ID => 42]])]
    public function testTransformWithMissingLocationId(array $env): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('%s not set or not a string', ConfigTransformerInterface::ENV_LOCATION_ID));

        $functionConfigTransformer = self::createStub(FunctionConfigTransformerInterface::class);

        $transformer = new ConfigTransformer($functionConfigTransformer);
        $transformer->transform($env);
    }
}
