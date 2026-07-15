<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

use ChristianBrown\CloudFunction\DataProviderInterface as BaseDataProviderInterface;

interface DataProviderInterface extends BaseDataProviderInterface
{
    public const ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT = 'relativeHumidityMeasurement';
    public const ID_VALUE_TEMPERATURE_MEASUREMENT = 'temperatureMeasurement';
    public const STALE_THRESHOLD = 24 * 60 * 60;
}
