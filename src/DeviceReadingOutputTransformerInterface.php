<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface DeviceReadingOutputTransformerInterface
{
    public const string KEY_HUMIDITY_STALE = 'humidityStale';
    public const string KEY_HUMIDITY_TIMESTAMP = 'humidityTimestamp';
    public const string KEY_HUMIDITY_VALUE = 'humidityValue';
    public const string KEY_NAME = 'name';
    public const string KEY_ROOM_NAME = 'roomName';
    public const string KEY_TEMPERATURE_STALE = 'temperatureStale';
    public const string KEY_TEMPERATURE_TIMESTAMP = 'temperatureTimestamp';
    public const string KEY_TEMPERATURE_VALUE = 'temperatureValue';

    /**
     * @return mixed[]
     */
    public function transform(DeviceReadingInterface $deviceReading): array;
}
