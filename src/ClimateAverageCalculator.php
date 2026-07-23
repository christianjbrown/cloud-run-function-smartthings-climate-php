<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use function array_filter;
use function array_map;
use function array_sum;
use function array_values;
use function count;

final class ClimateAverageCalculator implements ClimateAverageCalculatorInterface
{
    /**
     * @param DeviceReadingInterface[] $deviceReadings
     */
    public function averageHumidity(array $deviceReadings): ?float
    {
        return self::average(array_map(
            static fn (DeviceReadingInterface $deviceReading): ?MeasurementInterface => $deviceReading->getHumidity(),
            $deviceReadings
        ));
    }

    /**
     * @param DeviceReadingInterface[] $deviceReadings
     */
    public function averageTemperature(array $deviceReadings): ?float
    {
        return self::average(array_map(
            static fn (DeviceReadingInterface $deviceReading): ?MeasurementInterface => $deviceReading->getTemperature(),
            $deviceReadings
        ));
    }

    /**
     * @param array<?MeasurementInterface> $measurements
     */
    private static function average(array $measurements): ?float
    {
        $values = array_values(array_filter(
            array_map(
                static fn (?MeasurementInterface $measurement): ?float => self::liveValue($measurement),
                $measurements
            ),
            static fn (?float $value): bool => null !== $value
        ));

        if ([] === $values) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * The non-stale measured value, or null when the measurement is absent,
     * stale, or has no value.
     */
    private static function liveValue(?MeasurementInterface $measurement): ?float
    {
        if (!$measurement instanceof MeasurementInterface) {
            return null;
        }
        if (true === $measurement->isStale()) {
            return null;
        }

        return $measurement->getValue();
    }
}
