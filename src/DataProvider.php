<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\Database\ClimateMeasurementRecorderInterface;
use ChristianBrown\Database\Entity\SmartThingsClimate;
use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use DateTimeImmutable;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function error_log;
use function in_array;

final class DataProvider implements DataProviderInterface
{
    private ClimateAverageCalculatorInterface $climateAverageCalculator;
    private ClimateMeasurementRecorderInterface $climateMeasurementRecorder;
    private DeviceApiInterface $devicesApi;
    private DeviceStatusApiInterface $deviceStatusApi;
    private string $locationId;
    private LocationRoomApiInterface $locationRoomApi;
    private int $now;
    private OutputTransformerInterface $outputTransformer;

    public function __construct(DeviceApiInterface $devicesApi, DeviceStatusApiInterface $deviceStatusApi, LocationRoomApiInterface $locationRoomApi, OutputTransformerInterface $outputTransformer, ClimateAverageCalculatorInterface $climateAverageCalculator, ClimateMeasurementRecorderInterface $climateMeasurementRecorder, string $locationId)
    {
        $this->devicesApi = $devicesApi;
        $this->deviceStatusApi = $deviceStatusApi;
        $this->locationRoomApi = $locationRoomApi;
        $this->outputTransformer = $outputTransformer;
        $this->climateAverageCalculator = $climateAverageCalculator;
        $this->climateMeasurementRecorder = $climateMeasurementRecorder;
        $this->locationId = $locationId;
        $this->now = time();
    }

    /**
     * @return mixed[]
     */
    public function getData(ServerRequestInterface $request): array
    {
        $readings = array_values(array_filter(array_map(
            fn (DeviceInterface $device): ?DeviceReading => $this->processDevice($device),
            $this->devicesApi->getMultiple($this->locationId)
        )));

        $this->recordClimate($readings);

        return $this->outputTransformer->transform($readings);
    }

    private function buildReading(DeviceInterface $device, bool $hasTemperature, bool $hasHumidity): ?DeviceReading
    {
        $deviceStatus = $this->deviceStatusApi->getOneByDevice($device);

        $temperature = $this->resolveTemperature($deviceStatus, $hasTemperature);
        $humidity = $this->resolveHumidity($deviceStatus, $hasHumidity);

        // array_filter (rather than `null === … && null === …`) so both the
        // "has a reading" and "no reading" outcomes are reachable code paths.
        if ([] === array_filter([$temperature, $humidity], static fn (?MeasurementInterface $measurement): bool => null !== $measurement)) {
            return null;
        }

        return new DeviceReading(
            $device->getLabel() ?? '',
            $this->resolveRoomName($device),
            $temperature,
            $humidity
        );
    }

    /**
     * @return string[]
     */
    private static function capabilityIds(DeviceInterface $device): array
    {
        return array_merge(
            [],
            ...array_map(
                static fn (DeviceComponentInterface $component): array => array_map(
                    static fn (DeviceComponentCapabilityInterface $capability): string => $capability->getId(),
                    $component->getCapabilities()
                ),
                $device->getComponents()
            )
        );
    }

    /**
     * @return array{0: bool, 1: bool} Whether the device supports [temperature, relative humidity] measurement
     */
    private static function detectSupportedMeasurements(DeviceInterface $device): array
    {
        $capabilityIds = self::capabilityIds($device);

        return [
            in_array(self::ID_VALUE_TEMPERATURE_MEASUREMENT, $capabilityIds, true),
            in_array(self::ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT, $capabilityIds, true),
        ];
    }

    private function processDevice(DeviceInterface $device): ?DeviceReading
    {
        [$hasTemperature, $hasHumidity] = self::detectSupportedMeasurements($device);
        if ([] === array_filter([$hasTemperature, $hasHumidity])) {
            return null;
        }

        return $this->buildReading($device, $hasTemperature, $hasHumidity);
    }

    /**
     * Best-effort persistence of the average house temperature/humidity. The
     * write is wrapped so a database failure is logged, never propagated — it
     * must not disturb the function's response.
     *
     * @param DeviceReadingInterface[] $readings
     */
    private function recordClimate(array $readings): void
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
                    ->setRecordedAt(new DateTimeImmutable())
                    ->setTemperature($temperature)
                    ->setHumidity($humidity)
            );
        } catch (Throwable $exception) {
            error_log('SmartThings climate write failed: '.$exception->getMessage());
        }
    }

    private function resolveHumidity(DeviceStatusInterface $deviceStatus, bool $hasHumidity): ?MeasurementInterface
    {
        if (!$hasHumidity) {
            return null;
        }
        $measurement = $deviceStatus->getRelativeHumidityMeasurement();
        if (!$measurement instanceof DeviceStatusRelativeHumidityMeasurementInterface) {
            return null;
        }

        $value = $measurement->getHumidity();
        $timestamp = $value->getTimestamp();

        return new Measurement($value->getValue(), $timestamp, $timestamp < $this->now - self::STALE_THRESHOLD);
    }

    private function resolveRoomName(DeviceInterface $device): ?string
    {
        if (null === $device->getRoomId()) {
            return null;
        }

        return $this->locationRoomApi->getOneByDevice($device)->getName();
    }

    private function resolveTemperature(DeviceStatusInterface $deviceStatus, bool $hasTemperature): ?MeasurementInterface
    {
        if (!$hasTemperature) {
            return null;
        }
        $measurement = $deviceStatus->getTemperatureMeasurement();
        if (!$measurement instanceof DeviceStatusTemperatureMeasurementInterface) {
            return null;
        }

        $value = $measurement->getTemperature();
        $timestamp = $value->getTimestamp();

        return new Measurement($value->getValue(), $timestamp, $timestamp < $this->now - self::STALE_THRESHOLD);
    }
}
