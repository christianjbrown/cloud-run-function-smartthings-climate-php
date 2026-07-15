<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface OutputTransformerInterface
{
    public const KEY_AVERAGE_HUMIDITY_TIMESTAMP = 'averageHumidityTimestamp';
    public const KEY_AVERAGE_HUMIDITY_VALUE = 'averageHumidity';
    public const KEY_AVERAGE_TEMPERATURE_TIMESTAMP = 'averageTempTimestamp';
    public const KEY_AVERAGE_TEMPERATURE_VALUE = 'averageTempDegrees';
    public const KEY_DEVICES = 'devices';

    /**
     * @param DeviceReadingInterface[] $deviceTemperatures
     *
     * @return mixed[]
     */
    public function transform(array $deviceTemperatures): array;
}
