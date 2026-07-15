<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps\Tests;

use ChristianBrown\GetSmartHomeTemps\DeviceReadingInterface;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingOutputTransformer;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingOutputTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceReadingOutputTransformer::class)]
final class DeviceReadingOutputTransformerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testHumidityOnlyOmitsTemperatureKeys(): void
    {
        $reading = $this->createReading('test-label', null, null, null, null, 55.0, 28000, false);

        $expected = [
            DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY => 55.0,
            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
        ];

        $actual = (new DeviceReadingOutputTransformer())->transform($reading);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testTemperatureAndHumidity(): void
    {
        $reading = $this->createReading('test-label', 'test-room', 42.2, 29000, true, 55.0, 28000, false);

        $expected = [
            DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'test-room',
            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE => 42.2,
            DeviceReadingOutputTransformerInterface::KEY_TIMESTAMP => 29000,
            DeviceReadingOutputTransformerInterface::KEY_STALE => true,
            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY => 55.0,
            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 28000,
            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
        ];

        $actual = (new DeviceReadingOutputTransformer())->transform($reading);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testTemperatureOnlyOmitsHumidityKeys(): void
    {
        $reading = $this->createReading('test-label', null, 42.2, 29000, true, null, null, null);

        $expected = [
            DeviceReadingOutputTransformerInterface::KEY_LABEL => 'test-label',
            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE => 42.2,
            DeviceReadingOutputTransformerInterface::KEY_TIMESTAMP => 29000,
            DeviceReadingOutputTransformerInterface::KEY_STALE => true,
        ];

        $actual = (new DeviceReadingOutputTransformer())->transform($reading);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    private function createReading(string $label, ?string $roomName, ?float $temperature, ?int $timestamp, ?bool $stale, ?float $humidity, ?int $humidityTimestamp, ?bool $humidityStale): DeviceReadingInterface
    {
        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getLabel')
            ->willReturn($label);
        $reading->method('getRoomName')
            ->willReturn($roomName);
        $reading->method('getTemperature')
            ->willReturn($temperature);
        $reading->method('getTimestamp')
            ->willReturn($timestamp);
        $reading->method('isStale')
            ->willReturn($stale);
        $reading->method('getHumidity')
            ->willReturn($humidity);
        $reading->method('getHumidityTimestamp')
            ->willReturn($humidityTimestamp);
        $reading->method('isHumidityStale')
            ->willReturn($humidityStale);

        return $reading;
    }
}
