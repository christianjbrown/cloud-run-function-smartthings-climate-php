<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface DeviceReadingOutputTransformerInterface
{
    public const KEY_BATTERY_VALUE = 'batteryValue';
    public const KEY_HUMIDITY_STALE = 'humidityStale';
    public const KEY_HUMIDITY_TIMESTAMP = 'humidityTimestamp';
    public const KEY_HUMIDITY_VALUE = 'humidityValue';
    public const KEY_NAME = 'name';
    public const KEY_ROOM_NAME = 'roomName';
    public const KEY_TEMPERATURE_STALE = 'temperatureStale';
    public const KEY_TEMPERATURE_TIMESTAMP = 'temperatureTimestamp';
    public const KEY_TEMPERATURE_VALUE = 'temperatureValue';

    /**
     * @return mixed[]
     */
    public function transform(DeviceReadingInterface $deviceReading): array;
}
