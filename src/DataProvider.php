<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function in_array;

final class DataProvider implements DataProviderInterface
{
    private DeviceApiInterface $devicesApi;
    private DeviceStatusApiInterface $deviceStatusApi;
    private string $locationId;
    private LocationRoomApiInterface $locationRoomApi;
    private int $now;
    private OutputTransformerInterface $outputTransformer;

    public function __construct(DeviceApiInterface $devicesApi, DeviceStatusApiInterface $deviceStatusApi, LocationRoomApiInterface $locationRoomApi, OutputTransformerInterface $outputTransformer, string $locationId)
    {
        $this->devicesApi = $devicesApi;
        $this->deviceStatusApi = $deviceStatusApi;
        $this->locationRoomApi = $locationRoomApi;
        $this->outputTransformer = $outputTransformer;
        $this->locationId = $locationId;
        $this->now = time();
    }

    public function getData(ServerRequestInterface $request): array
    {
        $readings = array_map(
            fn (DeviceInterface $device): ?DeviceReading => $this->processDevice($device),
            $this->devicesApi->getMultiple($this->locationId)
        );

        return $this->outputTransformer->transform(array_values(array_filter($readings)));
    }

    private function buildReading(DeviceInterface $device, bool $hasTemperature, bool $hasHumidity): ?DeviceReading
    {
        $deviceStatus = $this->deviceStatusApi->getOneByDevice($device);

        [$temperature, $temperatureTimestamp, $temperatureStale] = $this->resolveTemperature($deviceStatus, $hasTemperature);
        [$humidity, $humidityTimestamp, $humidityStale] = $this->resolveHumidity($deviceStatus, $hasHumidity);

        // array_filter (rather than `null === … && null === …`) so both the
        // "has a reading" and "no reading" outcomes are reachable code paths.
        if ([] === array_filter([$temperature, $humidity], static fn ($value): bool => null !== $value)) {
            return null;
        }

        return new DeviceReading(
            $device->getLabel() ?? '',
            $this->resolveRoomName($device),
            $this->resolveBattery($deviceStatus),
            $temperature,
            $temperatureTimestamp,
            $temperatureStale,
            $humidity,
            $humidityTimestamp,
            $humidityStale
        );
    }

    /**
     * @return string[]
     */
    private function capabilityIds(DeviceInterface $device): array
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
    private function detectSupportedMeasurements(DeviceInterface $device): array
    {
        $capabilityIds = $this->capabilityIds($device);

        return [
            in_array(self::ID_VALUE_TEMPERATURE_MEASUREMENT, $capabilityIds, true),
            in_array(self::ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT, $capabilityIds, true),
        ];
    }

    private function processDevice(DeviceInterface $device): ?DeviceReading
    {
        [$hasTemperature, $hasHumidity] = $this->detectSupportedMeasurements($device);
        if ([] === array_filter([$hasTemperature, $hasHumidity])) {
            return null;
        }

        return $this->buildReading($device, $hasTemperature, $hasHumidity);
    }

    private function resolveBattery(DeviceStatusInterface $deviceStatus): ?int
    {
        $battery = $deviceStatus->getBattery();
        if (null === $battery) {
            return null;
        }

        return $battery->getBattery()->getValue();
    }

    /**
     * @return array{0: null|float, 1: null|int, 2: null|bool} The [value, timestamp, stale] of the reading, or nulls
     */
    private function resolveHumidity(DeviceStatusInterface $deviceStatus, bool $hasHumidity): array
    {
        if (!$hasHumidity) {
            return [null, null, null];
        }
        $measurement = $deviceStatus->getRelativeHumidityMeasurement();
        if (!$measurement instanceof DeviceStatusRelativeHumidityMeasurementInterface) {
            return [null, null, null];
        }

        $value = $measurement->getHumidity();
        $timestamp = $value->getTimestamp();

        return [$value->getValue(), $timestamp, $timestamp < $this->now - self::STALE_THRESHOLD];
    }

    private function resolveRoomName(DeviceInterface $device): ?string
    {
        if (null === $device->getRoomId()) {
            return null;
        }

        return $this->locationRoomApi->getOneByDevice($device)->getName();
    }

    /**
     * @return array{0: null|float, 1: null|int, 2: null|bool} The [value, timestamp, stale] of the reading, or nulls
     */
    private function resolveTemperature(DeviceStatusInterface $deviceStatus, bool $hasTemperature): array
    {
        if (!$hasTemperature) {
            return [null, null, null];
        }
        $measurement = $deviceStatus->getTemperatureMeasurement();
        if (!$measurement instanceof DeviceStatusTemperatureMeasurementInterface) {
            return [null, null, null];
        }

        $value = $measurement->getTemperature();
        $timestamp = $value->getTimestamp();

        return [$value->getValue(), $timestamp, $timestamp < $this->now - self::STALE_THRESHOLD];
    }
}
