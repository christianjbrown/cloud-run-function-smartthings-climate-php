<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\GcpFunction\DataProviderInterface as BaseDataProviderInterface;

interface DataProviderInterface extends BaseDataProviderInterface
{
    public const string ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT = 'relativeHumidityMeasurement';
    public const string ID_VALUE_TEMPERATURE_MEASUREMENT = 'temperatureMeasurement';
    public const int STALE_THRESHOLD = 24 * 60 * 60;
}
