<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Api\LocationRoomApiInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use Psr\Clock\ClockInterface;

use function array_filter;

final class DeviceReadingBuilder implements DeviceReadingBuilderInterface
{
    private ClockInterface $clock;
    private DeviceStatusApiInterface $deviceStatusApi;
    private LocationRoomApiInterface $locationRoomApi;

    public function __construct(DeviceStatusApiInterface $deviceStatusApi, LocationRoomApiInterface $locationRoomApi, ClockInterface $clock)
    {
        $this->clock = $clock;
        $this->deviceStatusApi = $deviceStatusApi;
        $this->locationRoomApi = $locationRoomApi;
    }

    public function build(DeviceInterface $device, SupportedMeasurementsInterface $supportedMeasurements): ?DeviceReadingInterface
    {
        $deviceStatus = $this->deviceStatusApi->getOneByDevice($device);
        $now = $this->clock->now()->getTimestamp();

        $temperature = self::resolveTemperature($deviceStatus, $supportedMeasurements->supportsTemperature(), $now);
        $humidity = self::resolveHumidity($deviceStatus, $supportedMeasurements->supportsHumidity(), $now);

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

    private static function resolveHumidity(DeviceStatusInterface $deviceStatus, bool $supported, int $now): ?MeasurementInterface
    {
        if (!$supported) {
            return null;
        }
        $measurement = $deviceStatus->getRelativeHumidityMeasurement();
        if (!$measurement instanceof DeviceStatusRelativeHumidityMeasurementInterface) {
            return null;
        }

        $value = $measurement->getHumidity();
        $timestamp = $value->getTimestamp();

        return new Measurement($value->getValue(), $timestamp, $timestamp < $now - self::STALE_THRESHOLD);
    }

    private function resolveRoomName(DeviceInterface $device): ?string
    {
        if (null === $device->getRoomId()) {
            return null;
        }

        return $this->locationRoomApi->getOneByDevice($device)->getName();
    }

    private static function resolveTemperature(DeviceStatusInterface $deviceStatus, bool $supported, int $now): ?MeasurementInterface
    {
        if (!$supported) {
            return null;
        }
        $measurement = $deviceStatus->getTemperatureMeasurement();
        if (!$measurement instanceof DeviceStatusTemperatureMeasurementInterface) {
            return null;
        }

        $value = $measurement->getTemperature();
        $timestamp = $value->getTimestamp();

        return new Measurement($value->getValue(), $timestamp, $timestamp < $now - self::STALE_THRESHOLD);
    }
}
