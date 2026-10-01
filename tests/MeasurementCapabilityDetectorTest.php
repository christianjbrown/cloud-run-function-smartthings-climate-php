<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThingsClimate\MeasurementCapabilityDetector;
use ChristianBrown\SmartThingsClimate\SupportedMeasurements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(MeasurementCapabilityDetector::class)]
#[CoversClass(SupportedMeasurements::class)]
final class MeasurementCapabilityDetectorTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testComponentWithoutCapabilities(): void
    {
        $supported = (new MeasurementCapabilityDetector())->detect($this->createDevice([[]]));

        self::assertFalse($supported->hasAny());
    }

    /**
     * @throws Exception
     */
    public function testNoComponents(): void
    {
        $supported = (new MeasurementCapabilityDetector())->detect($this->createDevice([]));

        self::assertFalse($supported->supportsTemperature());
        self::assertFalse($supported->supportsHumidity());
    }

    /**
     * @throws Exception
     */
    public function testTemperatureAndHumidity(): void
    {
        $supported = (new MeasurementCapabilityDetector())->detect($this->createDevice([['temperatureMeasurement', 'relativeHumidityMeasurement']]));

        self::assertTrue($supported->supportsTemperature());
        self::assertTrue($supported->supportsHumidity());
    }

    /**
     * @throws Exception
     */
    public function testTemperatureOnlyAcrossComponents(): void
    {
        $supported = (new MeasurementCapabilityDetector())->detect($this->createDevice([['other'], ['temperatureMeasurement', 'other']]));

        self::assertTrue($supported->supportsTemperature());
        self::assertFalse($supported->supportsHumidity());
    }

    /**
     * @param string[][] $componentCapabilityIds
     *
     * @throws Exception
     */
    private function createDevice(array $componentCapabilityIds): DeviceInterface
    {
        $components = [];
        foreach ($componentCapabilityIds as $capabilityIds) {
            $capabilities = [];
            foreach ($capabilityIds as $id) {
                $capability = self::createStub(DeviceComponentCapabilityInterface::class);
                $capability->method('getId')
                    ->willReturn($id);
                $capabilities[] = $capability;
            }
            $component = self::createStub(DeviceComponentInterface::class);
            $component->method('getCapabilities')
                ->willReturn($capabilities);
            $components[] = $component;
        }

        $device = self::createStub(DeviceInterface::class);
        $device->method('getComponents')
            ->willReturn($components);

        return $device;
    }
}
