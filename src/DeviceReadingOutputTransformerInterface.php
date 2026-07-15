<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

interface DeviceReadingOutputTransformerInterface
{
    public const KEY_HUMIDITY = 'humidity';
    public const KEY_HUMIDITY_STALE = 'humidityStale';
    public const KEY_HUMIDITY_TIMESTAMP = 'humidityTimestamp';
    public const KEY_LABEL = 'name';
    public const KEY_STALE = 'stale';
    public const KEY_TEMPERATURE = 'temp';
    public const KEY_TIMESTAMP = 'timestamp';

    public function transform(DeviceReadingInterface $deviceReading): array;
}
