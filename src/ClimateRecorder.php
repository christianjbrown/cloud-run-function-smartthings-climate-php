<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\Database\ClimateMeasurementRecorderInterface;
use ChristianBrown\Database\Entity\SmartThingsClimate;
use Psr\Clock\ClockInterface;
use Throwable;

use function array_filter;
use function error_log;

/**
 * Best-effort persistence of the average house temperature/humidity. The
 * write is wrapped so a database failure is logged, never propagated: it
 * must not disturb the function's response.
 */
final class ClimateRecorder implements ClimateRecorderInterface
{
    private ClimateAverageCalculatorInterface $climateAverageCalculator;
    private ClimateMeasurementRecorderInterface $climateMeasurementRecorder;
    private ClockInterface $clock;

    public function __construct(ClimateAverageCalculatorInterface $climateAverageCalculator, ClimateMeasurementRecorderInterface $climateMeasurementRecorder, ClockInterface $clock)
    {
        $this->climateAverageCalculator = $climateAverageCalculator;
        $this->climateMeasurementRecorder = $climateMeasurementRecorder;
        $this->clock = $clock;
    }

    /**
     * @param DeviceReadingInterface[] $readings
     */
    public function record(array $readings): void
    {
        $temperature = $this->climateAverageCalculator->averageTemperature($readings);
        $humidity = $this->climateAverageCalculator->averageHumidity($readings);

        // Nothing worth recording when every reading is absent or stale.
        if ([] === array_filter([$temperature, $humidity], static fn (?float $value): bool => null !== $value)) {
            return;
        }

        try {
            $this->climateMeasurementRecorder->record(
                (new SmartThingsClimate())
                    ->setRecordedAt($this->clock->now())
                    ->setTemperature($temperature)
                    ->setHumidity($humidity)
            );
        } catch (Throwable $exception) {
            error_log('SmartThings climate write failed: '.$exception->getMessage());
        }
    }
}
