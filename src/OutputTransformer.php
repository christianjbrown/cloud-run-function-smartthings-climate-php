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

        $devicesData = array_map(
            fn (DeviceReadingInterface $deviceReading): array => $this->deviceReadingOutputTransformer->transform($deviceReading),
            $deviceReadings
        );

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
        $fresh = array_filter(
            $deviceReadings,
            static fn (DeviceReadingInterface $deviceReading): bool => null !== $deviceReading->getHumidity() && !$deviceReading->isHumidityStale()
        );

        return $this->average(
            array_map(static fn (DeviceReadingInterface $deviceReading): float => (float) $deviceReading->getHumidity(), $fresh),
            array_map(static fn (DeviceReadingInterface $deviceReading): int => (int) $deviceReading->getHumidityTimestamp(), $fresh),
            self::KEY_AVERAGE_HUMIDITY_VALUE,
            self::KEY_AVERAGE_HUMIDITY_TIMESTAMP
        );
    }

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     *
     * @return mixed[]
     */
    private function buildTemperatureAverage(array $deviceReadings): array
    {
        $fresh = array_filter(
            $deviceReadings,
            static fn (DeviceReadingInterface $deviceReading): bool => null !== $deviceReading->getTemperature() && !$deviceReading->isStale()
        );

        return $this->average(
            array_map(static fn (DeviceReadingInterface $deviceReading): float => (float) $deviceReading->getTemperature(), $fresh),
            array_map(static fn (DeviceReadingInterface $deviceReading): int => (int) $deviceReading->getTimestamp(), $fresh),
            self::KEY_AVERAGE_TEMPERATURE_VALUE,
            self::KEY_AVERAGE_TEMPERATURE_TIMESTAMP
        );
    }

    /**
     * @param float[] $values
     * @param int[]   $timestamps
     *
     * @return mixed[]
     */
    private function average(array $values, array $timestamps, string $valueKey, string $timestampKey): array
    {
        if ([] === $timestamps) {
            return [];
        }

        return [
            $valueKey => array_sum($values) / count($values),
            $timestampKey => min($timestamps),
        ];
    }
}
