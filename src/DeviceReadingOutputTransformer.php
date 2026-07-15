<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

final class DeviceReadingOutputTransformer implements DeviceReadingOutputTransformerInterface
{
    public function transform(DeviceReadingInterface $deviceReading): array
    {
        $data = [
            self::KEY_LABEL => $deviceReading->getLabel(),
        ];

        if (null !== $deviceReading->getTemperature()) {
            $data[self::KEY_TEMPERATURE] = $deviceReading->getTemperature();
            $data[self::KEY_TIMESTAMP] = $deviceReading->getTimestamp();
            $data[self::KEY_STALE] = $deviceReading->isStale();
        }

        if (null !== $deviceReading->getHumidity()) {
            $data[self::KEY_HUMIDITY] = $deviceReading->getHumidity();
            $data[self::KEY_HUMIDITY_TIMESTAMP] = $deviceReading->getHumidityTimestamp();
            $data[self::KEY_HUMIDITY_STALE] = $deviceReading->isHumidityStale();
        }

        return $data;
    }
}
