<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Model\DeviceInterface;

interface MeasurementCapabilityDetectorInterface
{
    public const string ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT = 'relativeHumidityMeasurement';
    public const string ID_VALUE_TEMPERATURE_MEASUREMENT = 'temperatureMeasurement';

    public function detect(DeviceInterface $device): SupportedMeasurementsInterface;
}
