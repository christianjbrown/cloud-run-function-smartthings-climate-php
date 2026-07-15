<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps\Tests;

use ChristianBrown\GetSmartHomeTemps\DeviceReadingInterface;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingOutputTransformer;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingOutputTransformerInterface;
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
    public function testTransform(array $expected, ?string $roomName, ?float $temperature, ?bool $temperatureStale, ?float $humidity, ?bool $humidityStale): void
    {
        $reading = $this->createReading('test-label', $roomName, $temperature, $temperatureStale, $humidity, $humidityStale);

        $actual = (new DeviceReadingOutputTransformer())->transform($reading);

        self::assertSame($expected, $actual);
    }

    /**
     * Every combination of room name / temperature / humidity being present or
     * absent, so each optional output block is exercised in both directions.
     *
     * @return iterable<string, mixed[]>
     */
    public static function provideTransformCases(): iterable
    {
        yield 'nothing but the label' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
            ],
            null, null, null, null, null,
        ];
        yield 'room only' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
            ],
            'test-room', null, null, null, null,
        ];
        yield 'temperature only' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE => 42.2,
                DeviceReadingOutputTransformerInterface::KEY_TIMESTAMP => 29000,
                DeviceReadingOutputTransformerInterface::KEY_STALE => false,
            ],
            null, 42.2, false, null, null,
        ];
        yield 'humidity only' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY => 55.0,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
            ],
            null, null, null, 55.0, false,
        ];
        yield 'room and temperature' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE => 42.2,
                DeviceReadingOutputTransformerInterface::KEY_TIMESTAMP => 29000,
                DeviceReadingOutputTransformerInterface::KEY_STALE => false,
            ],
            'test-room', 42.2, false, null, null,
        ];
        yield 'room and humidity' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY => 55.0,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
            ],
            'test-room', null, null, 55.0, false,
        ];
        yield 'temperature and humidity' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE => 42.2,
                DeviceReadingOutputTransformerInterface::KEY_TIMESTAMP => 29000,
                DeviceReadingOutputTransformerInterface::KEY_STALE => false,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY => 55.0,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
            ],
            null, 42.2, false, 55.0, false,
        ];
        yield 'room, temperature and humidity' => [
            [
                DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
                DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
                DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE => 42.2,
                DeviceReadingOutputTransformerInterface::KEY_TIMESTAMP => 29000,
                DeviceReadingOutputTransformerInterface::KEY_STALE => false,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY => 55.0,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
                DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
            ],
            'test-room', 42.2, false, 55.0, false,
        ];
    }

    /**
     * @throws Exception
     */
    private function createReading(string $label, ?string $roomName, ?float $temperature, ?bool $temperatureStale, ?float $humidity, ?bool $humidityStale): DeviceReadingInterface
    {
        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getLabel')
            ->willReturn($label);
        $reading->method('getRoomName')
            ->willReturn($roomName);
        $reading->method('getTemperature')
            ->willReturn($temperature);
        $reading->method('getTimestamp')
            ->willReturn(null === $temperature ? null : 29000);
        $reading->method('isStale')
            ->willReturn($temperatureStale);
        $reading->method('getHumidity')
            ->willReturn($humidity);
        $reading->method('getHumidityTimestamp')
            ->willReturn(null === $humidity ? null : 28000);
        $reading->method('isHumidityStale')
            ->willReturn($humidityStale);

        return $reading;
    }
}
