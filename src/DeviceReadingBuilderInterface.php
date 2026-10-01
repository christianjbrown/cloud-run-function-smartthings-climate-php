<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Model\DeviceInterface;

interface DeviceReadingBuilderInterface
{
    public const int STALE_THRESHOLD = 24 * 60 * 60;

    /**
     * The device's reading, or null when it has no measurement to report.
     */
    public function build(DeviceInterface $device, SupportedMeasurementsInterface $supportedMeasurements): ?DeviceReadingInterface;
}
