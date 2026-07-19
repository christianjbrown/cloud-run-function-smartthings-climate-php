<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

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
        $data += $this->temperature($deviceReading);
        $data += $this->humidity($deviceReading);

        return $data;
    }

    /**
     * @return mixed[]
     */
    private function humidity(DeviceReadingInterface $deviceReading): array
    {
        $humidity = $deviceReading->getHumidity();
        if (null === $humidity) {
            return [];
        }

        return [
            self::KEY_HUMIDITY_VALUE => $humidity->getValue(),
            self::KEY_HUMIDITY_TIMESTAMP => $humidity->getTimestamp(),
            self::KEY_HUMIDITY_STALE => $humidity->isStale(),
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
        $temperature = $deviceReading->getTemperature();
        if (null === $temperature) {
            return [];
        }

        return [
            self::KEY_TEMPERATURE_VALUE => $temperature->getValue(),
            self::KEY_TEMPERATURE_TIMESTAMP => $temperature->getTimestamp(),
            self::KEY_TEMPERATURE_STALE => $temperature->isStale(),
        ];
    }
}
