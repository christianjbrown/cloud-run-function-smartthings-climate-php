<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

final class OutputTransformer implements OutputTransformerInterface
{
    private DeviceReadingOutputTransformerInterface $deviceReadingOutputTransformer;

    public function __construct(DeviceReadingOutputTransformerInterface $deviceReadingOutputTransformer)
    {
        $this->deviceReadingOutputTransformer = $deviceReadingOutputTransformer;
    }

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     *
     * @return mixed[]
     */
    public function transform(array $deviceReadings): array
    {
        usort(
            $deviceReadings,
            static fn (DeviceReadingInterface $a, DeviceReadingInterface $b) => strcmp($a->getLabel(), $b->getLabel())
        );

        $devicesData = [];
        foreach ($deviceReadings as $deviceReading) {
            $devicesData[] = $this->deviceReadingOutputTransformer->transform($deviceReading);
        }

        $data = [self::KEY_DEVICES => $devicesData];
        $data += $this->buildTemperatureAverage($deviceReadings);
        $data += $this->buildHumidityAverage($deviceReadings);

        return $data;
    }

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     *
     * @return mixed[]
     */
    private function buildHumidityAverage(array $deviceReadings): array
    {
        $total = 0;
        $timestamps = [];
        foreach ($deviceReadings as $deviceReading) {
            if (null === $deviceReading->getHumidity() || $deviceReading->isHumidityStale()) {
                continue;
            }
            $total += $deviceReading->getHumidity();
            $timestamps[] = $deviceReading->getHumidityTimestamp();
        }

        if ([] === $timestamps) {
            return [];
        }

        return [
            self::KEY_AVERAGE_HUMIDITY_VALUE => $total / count($timestamps),
            self::KEY_AVERAGE_HUMIDITY_TIMESTAMP => min($timestamps),
        ];
    }

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     *
     * @return mixed[]
     */
    private function buildTemperatureAverage(array $deviceReadings): array
    {
        $total = 0;
        $timestamps = [];
        foreach ($deviceReadings as $deviceReading) {
            if (null === $deviceReading->getTemperature() || $deviceReading->isStale()) {
                continue;
            }
            $total += $deviceReading->getTemperature();
            $timestamps[] = $deviceReading->getTimestamp();
        }

        if ([] === $timestamps) {
            return [];
        }

        return [
            self::KEY_AVERAGE_TEMPERATURE_VALUE => $total / count($timestamps),
            self::KEY_AVERAGE_TEMPERATURE_TIMESTAMP => min($timestamps),
        ];
    }
}
