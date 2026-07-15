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
            static fn (DeviceReadingInterface $a, DeviceReadingInterface $b) => strcmp($a->getName(), $b->getName())
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

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     *
     * @return mixed[]
     */
    private function buildHumidityAverage(array $deviceReadings): array
    {
        $fresh = array_filter(
            $deviceReadings,
            static fn (DeviceReadingInterface $deviceReading): bool => null !== $deviceReading->getHumidityValue() && !$deviceReading->isHumidityStale()
        );

        return $this->average(
            array_map(static fn (DeviceReadingInterface $deviceReading): float => (float) $deviceReading->getHumidityValue(), $fresh),
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
            static fn (DeviceReadingInterface $deviceReading): bool => null !== $deviceReading->getTemperatureValue() && !$deviceReading->isTemperatureStale()
        );

        return $this->average(
            array_map(static fn (DeviceReadingInterface $deviceReading): float => (float) $deviceReading->getTemperatureValue(), $fresh),
            array_map(static fn (DeviceReadingInterface $deviceReading): int => (int) $deviceReading->getTemperatureTimestamp(), $fresh),
            self::KEY_AVERAGE_TEMPERATURE_VALUE,
            self::KEY_AVERAGE_TEMPERATURE_TIMESTAMP
        );
    }
}
