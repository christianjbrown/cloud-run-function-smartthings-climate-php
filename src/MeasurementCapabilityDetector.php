<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;

use function array_map;
use function array_merge;
use function in_array;

final class MeasurementCapabilityDetector implements MeasurementCapabilityDetectorInterface
{
    public function detect(DeviceInterface $device): SupportedMeasurementsInterface
    {
        $capabilityIds = self::capabilityIds($device);

        return new SupportedMeasurements(
            in_array(self::ID_VALUE_TEMPERATURE_MEASUREMENT, $capabilityIds, true),
            in_array(self::ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT, $capabilityIds, true),
        );
    }

    /**
     * @return string[]
     */
    private static function capabilityIds(DeviceInterface $device): array
    {
        return array_merge(
            [],
            ...array_map(
                static fn (DeviceComponentInterface $component): array => array_map(
                    static fn (DeviceComponentCapabilityInterface $capability): string => $capability->getId(),
                    $component->getCapabilities()
                ),
                $device->getComponents()
            )
        );
    }
}
