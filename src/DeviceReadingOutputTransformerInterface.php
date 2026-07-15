<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

interface DeviceReadingOutputTransformerInterface
{
    public const KEY_HUMIDITY = 'humidityValue';
    public const KEY_HUMIDITY_STALE = 'humidityStale';
    public const KEY_HUMIDITY_TIMESTAMP = 'humidityTimestamp';
    public const KEY_LABEL = 'name';
    public const KEY_ROOM_NAME = 'roomName';
    public const KEY_STALE = 'temperatureStale';
    public const KEY_TEMPERATURE = 'temperatureValue';
    public const KEY_TIMESTAMP = 'temperatureTimestamp';

    /**
     * @return mixed[]
     */
    public function transform(DeviceReadingInterface $deviceReading): array;
}
