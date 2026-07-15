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

    public function transform(array $deviceReadings): array
    {
        // @todo Doesn't check if the things in the array are really a DeviceReadingInterface
        usort(
            $deviceReadings,
            static fn ($a, $b) => strcmp($a->getLabel(), $b->getLabel())
        );

        $devicesData = [];
        foreach ($deviceReadings as $deviceReading) {
            if ($deviceReading instanceof DeviceReadingInterface) {
                $devicesData[] = $this->deviceReadingOutputTransformer->transform($deviceReading);
            }
        }

        $data = [self::KEY_DEVICES => $devicesData];
        $data += $this->buildTemperatureAverage($deviceReadings);
        $data += $this->buildHumidityAverage($deviceReadings);

        return $data;
    }

    private function buildHumidityAverage(array $deviceReadings): array
    {
        $total = 0;
        $timestamps = [];
        foreach ($deviceReadings as $deviceReading) {
            if (!$deviceReading instanceof DeviceReadingInterface || null === $deviceReading->getHumidity() || $deviceReading->isHumidityStale()) {
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

    private function buildTemperatureAverage(array $deviceReadings): array
    {
        $total = 0;
        $timestamps = [];
        foreach ($deviceReadings as $deviceReading) {
            if (!$deviceReading instanceof DeviceReadingInterface || null === $deviceReading->getTemperature() || $deviceReading->isStale()) {
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
