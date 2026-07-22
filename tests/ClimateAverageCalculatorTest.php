<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThingsClimate\ClimateAverageCalculator;
use ChristianBrown\SmartThingsClimate\DeviceReadingInterface;
use ChristianBrown\SmartThingsClimate\MeasurementInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClimateAverageCalculator::class)]
final class ClimateAverageCalculatorTest extends TestCase
{
    public function testAveragesAreNullWhenNoFreshValues(): void
    {
        $calculator = new ClimateAverageCalculator();

        self::assertNull($calculator->averageTemperature([]));
        self::assertNull($calculator->averageHumidity([]));
    }

    /**
     * @throws Exception
     */
    public function testAveragesUseOnlyFreshValuedMeasurements(): void
    {
        // A: fresh temperature, no humidity.
        $readingA = $this->createReading($this->createMeasurement(20.0, false), null);
        // B: stale temperature (excluded), fresh humidity.
        $readingB = $this->createReading($this->createMeasurement(99.0, true), $this->createMeasurement(50.0, false));
        // C: fresh temperature with no value (excluded), fresh humidity.
        $readingC = $this->createReading($this->createMeasurement(null, false), $this->createMeasurement(60.0, false));
        // D: no measurements at all.
        $readingD = $this->createReading(null, null);

        $readings = [$readingA, $readingB, $readingC, $readingD];

        $calculator = new ClimateAverageCalculator();

        self::assertSame(20.0, $calculator->averageTemperature($readings));
        self::assertSame(55.0, $calculator->averageHumidity($readings));
    }

    /**
     * @throws Exception
     */
    private function createMeasurement(?float $value, bool $stale): MeasurementInterface
    {
        $measurement = self::createStub(MeasurementInterface::class);
        $measurement->method('getValue')
            ->willReturn($value);
        $measurement->method('isStale')
            ->willReturn($stale);

        return $measurement;
    }

    /**
     * @throws Exception
     */
    private function createReading(?MeasurementInterface $temperature, ?MeasurementInterface $humidity): DeviceReadingInterface
    {
        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getTemperature')
            ->willReturn($temperature);
        $reading->method('getHumidity')
            ->willReturn($humidity);

        return $reading;
    }
}
