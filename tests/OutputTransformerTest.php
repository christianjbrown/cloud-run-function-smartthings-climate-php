<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps\Tests;

use ChristianBrown\GetSmartHomeTemps\DeviceReadingInterface;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingOutputTransformerInterface;
use ChristianBrown\GetSmartHomeTemps\OutputTransformer;
use ChristianBrown\GetSmartHomeTemps\OutputTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputTransformer::class)]
final class OutputTransformerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test(): void
    {
        // temp: stale, humidity: fresh
        $reading1 = $this->createReading('test-device-1', 42.2, 29000, true, 30.0, 28000, false);
        // temp: fresh, humidity: fresh
        $reading2 = $this->createReading('test-device-2', 52.2, 39000, false, 40.0, 38000, false);
        // temp: fresh, humidity: stale
        $reading3 = $this->createReading('test-device-3', 62.2, 49000, false, 90.0, 9000, true);
        // temp: stale, humidity: none
        $reading4 = $this->createReading('test-device-4', 72.2, 59000, true, null, null, null);
        // humidity-only: no temperature, humidity fresh
        $reading5 = $this->createReading('test-device-5', null, null, null, 50.0, 48000, false);

        $deviceReadingOutputTransformer = self::createStub(DeviceReadingOutputTransformerInterface::class);
        $deviceReadingOutputTransformer->method('transform')
            ->willReturnMap(
                [
                    [$reading1, ['test-device-1']],
                    [$reading2, ['test-device-2']],
                    [$reading3, ['test-device-3']],
                    [$reading4, ['test-device-4']],
                    [$reading5, ['test-device-5']],
                ]
            );

        $transformer = new OutputTransformer($deviceReadingOutputTransformer);

        $expected = [
            OutputTransformerInterface::KEY_DEVICES => [
                ['test-device-1'],
                ['test-device-2'],
                ['test-device-3'],
                ['test-device-4'],
                ['test-device-5'],
            ],
            // Non-stale temperatures: device-2 (52.2) + device-3 (62.2) -> avg 57.2, earliest ts 39000
            OutputTransformerInterface::KEY_AVERAGE_TEMPERATURE_VALUE => 57.2,
            OutputTransformerInterface::KEY_AVERAGE_TEMPERATURE_TIMESTAMP => 39000,
            // Non-stale humidities: device-1 (30) + device-2 (40) + device-5 (50) -> avg 40, earliest ts 28000
            OutputTransformerInterface::KEY_AVERAGE_HUMIDITY_VALUE => 40.0,
            OutputTransformerInterface::KEY_AVERAGE_HUMIDITY_TIMESTAMP => 28000,
        ];

        $actual = $transformer->transform([$reading1, $reading2, $reading3, $reading4, $reading5]);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testAveragesOmittedWhenAllReadingsStale(): void
    {
        $reading1 = $this->createReading('test-device-1', 42.2, 29000, true, 30.0, 28000, true);
        $reading2 = $this->createReading('test-device-2', 52.2, 39000, true, 40.0, 38000, true);

        $deviceReadingOutputTransformer = self::createStub(DeviceReadingOutputTransformerInterface::class);
        $deviceReadingOutputTransformer->method('transform')
            ->willReturnMap(
                [
                    [$reading1, ['test-device-1']],
                    [$reading2, ['test-device-2']],
                ]
            );

        $transformer = new OutputTransformer($deviceReadingOutputTransformer);

        $expected = [
            OutputTransformerInterface::KEY_DEVICES => [
                ['test-device-1'],
                ['test-device-2'],
            ],
        ];

        $actual = $transformer->transform([$reading1, $reading2]);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    public function testEmptyReadingsProducesOnlyAnEmptyDeviceList(): void
    {
        $deviceReadingOutputTransformer = self::createStub(DeviceReadingOutputTransformerInterface::class);

        $transformer = new OutputTransformer($deviceReadingOutputTransformer);

        $expected = [
            OutputTransformerInterface::KEY_DEVICES => [],
        ];

        $actual = $transformer->transform([]);

        self::assertSame($expected, $actual);
    }

    /**
     * @throws Exception
     */
    private function createReading(string $label, ?float $temperature, ?int $timestamp, ?bool $stale, ?float $humidity, ?int $humidityTimestamp, ?bool $humidityStale): DeviceReadingInterface
    {
        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getLabel')
            ->willReturn($label);
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
