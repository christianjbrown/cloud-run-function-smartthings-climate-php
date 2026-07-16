<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThingsClimate\DeviceReadingInterface;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformer;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceReadingOutputTransformer::class)]
final class DeviceReadingOutputTransformerTest extends TestCase
{
    /**
     * @param mixed[] $expected
     *
     * @throws Exception
     */
    #[DataProvider('provideTransformCases')]
    public function testTransform(array $expected, ?string $roomName, ?float $temperatureValue, ?bool $temperatureStale, ?float $humidityValue, ?bool $humidityStale): void
    {
        $reading = $this->createReading('test-name', $roomName, $temperatureValue, $temperatureStale, $humidityValue, $humidityStale);

        $actual = (new DeviceReadingOutputTransformer())->transform($reading);

        self::assertSame($expected, $actual);
    }

    /**
     * Each optional block (room, temperature, humidity) is exercised both
     * present and absent so every output path is covered.
     *
     * @return iterable<string, mixed[]>
     */
    public static function provideTransformCases(): iterable
    {
        yield 'nothing but the name' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_NAME => 'test-name',
            ],
            null, null, null, null, null,
        ];
        yield 'room only' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_NAME => 'test-name',
                DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
            ],
            'test-room', null, null, null, null,
        ];
        yield 'temperature only' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_NAME => 'test-name',
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 42.2,
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 29000,
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
            ],
            null, 42.2, false, null, null,
        ];
        yield 'humidity only' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_NAME => 'test-name',
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_VALUE => 55.0,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
            ],
            null, null, null, 55.0, false,
        ];
        yield 'everything present' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_NAME => 'test-name',
                DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 42.2,
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 29000,
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_VALUE => 55.0,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
            ],
            'test-room', 42.2, false, 55.0, false,
        ];
    }

    /**
     * @throws Exception
     */
    private function createReading(string $name, ?string $roomName, ?float $temperatureValue, ?bool $temperatureStale, ?float $humidityValue, ?bool $humidityStale): DeviceReadingInterface
    {
        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getName')
            ->willReturn($name);
        $reading->method('getRoomName')
            ->willReturn($roomName);
        $reading->method('getTemperatureValue')
            ->willReturn($temperatureValue);
        $reading->method('getTemperatureTimestamp')
            ->willReturn(null === $temperatureValue ? null : 29000);
        $reading->method('isTemperatureStale')
            ->willReturn($temperatureStale);
        $reading->method('getHumidityValue')
            ->willReturn($humidityValue);
        $reading->method('getHumidityTimestamp')
            ->willReturn(null === $humidityValue ? null : 28000);
        $reading->method('isHumidityStale')
            ->willReturn($humidityStale);

        return $reading;
    }
}
