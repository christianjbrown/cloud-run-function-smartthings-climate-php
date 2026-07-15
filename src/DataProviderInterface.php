<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

use ChristianBrown\CloudFunction\DataProviderInterface as BaseDataProviderInterface;

interface DataProviderInterface extends BaseDataProviderInterface
{
    public const STALE_TEMPERATURE_THRESHOLD = 24 * 60 * 60;
    public const ID_VALUE_TEMPERATURE_MEASUREMENT = 'temperatureMeasurement';
}
