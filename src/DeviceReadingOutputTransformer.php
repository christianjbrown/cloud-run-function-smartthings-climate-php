<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

final class DeviceReadingOutputTransformer implements DeviceReadingOutputTransformerInterface
{
    /**
     * @return mixed[]
     */
    public function transform(DeviceReadingInterface $deviceReading): array
    {
        // Each optional block is a self-contained helper unioned onto the base,
        // so a block's presence/absence is an independent code path.
        $data = [self::KEY_NAME => $deviceReading->getName()];
        $data += $this->roomName($deviceReading);
        $data += $this->battery($deviceReading);
        $data += $this->temperature($deviceReading);
        $data += $this->humidity($deviceReading);

        return $data;
    }

    /**
     * @return mixed[]
     */
    private function battery(DeviceReadingInterface $deviceReading): array
    {
        if (null === $deviceReading->getBatteryValue()) {
            return [];
        }

        return [self::KEY_BATTERY_VALUE => $deviceReading->getBatteryValue()];
    }

    /**
     * @return mixed[]
     */
    private function humidity(DeviceReadingInterface $deviceReading): array
    {
        if (null === $deviceReading->getHumidityValue()) {
            return [];
        }

        return [
            self::KEY_HUMIDITY_VALUE => $deviceReading->getHumidityValue(),
            self::KEY_HUMIDITY_TIMESTAMP => $deviceReading->getHumidityTimestamp(),
            self::KEY_HUMIDITY_STALE => $deviceReading->isHumidityStale(),
        ];
    }

    /**
     * @return mixed[]
     */
    private function roomName(DeviceReadingInterface $deviceReading): array
    {
        if (null === $deviceReading->getRoomName()) {
            return [];
        }

        return [self::KEY_ROOM_NAME => $deviceReading->getRoomName()];
    }

    /**
     * @return mixed[]
     */
    private function temperature(DeviceReadingInterface $deviceReading): array
    {
        if (null === $deviceReading->getTemperatureValue()) {
            return [];
        }

        return [
            self::KEY_TEMPERATURE_VALUE => $deviceReading->getTemperatureValue(),
            self::KEY_TEMPERATURE_TIMESTAMP => $deviceReading->getTemperatureTimestamp(),
            self::KEY_TEMPERATURE_STALE => $deviceReading->isTemperatureStale(),
        ];
    }
}
