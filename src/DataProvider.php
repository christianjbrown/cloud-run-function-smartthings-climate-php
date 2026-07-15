<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

use ChristianBrown\SmartThings\Api\DeviceApiInterface;
use ChristianBrown\SmartThings\Api\DeviceStatusApiInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentCapabilityInterface;
use ChristianBrown\SmartThings\Model\DeviceComponentInterface;
use ChristianBrown\SmartThings\Model\DeviceInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusRelativeHumidityMeasurementInterface;
use ChristianBrown\SmartThings\Model\DeviceStatusTemperatureMeasurementInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DataProvider implements DataProviderInterface
{
    private DeviceApiInterface $devicesApi;
    private DeviceStatusApiInterface $deviceStatusApi;
    private int $now;
    private OutputTransformerInterface $outputTransformer;

    public function __construct(DeviceApiInterface $devicesApi, DeviceStatusApiInterface $deviceStatusApi, OutputTransformerInterface $outputTransformer)
    {
        $this->devicesApi = $devicesApi;
        $this->deviceStatusApi = $deviceStatusApi;
        $this->outputTransformer = $outputTransformer;
        $this->now = time();
    }

    public function getData(ServerRequestInterface $request): array
    {
        $devices = $this->devicesApi->getMultiple();
        $deviceReadings = [];

        foreach ($devices as $device) {
            $reading = $this->processDevice($device);
            if ($reading instanceof DeviceReadingInterface) {
                $deviceReadings[] = $reading;
            }
        }

        $data = $this->outputTransformer->transform($deviceReadings);

        return $data;
    }

    private function buildReading(DeviceInterface $device, bool $hasTemperature, bool $hasHumidity): ?DeviceReading
    {
        $deviceStatus = $this->deviceStatusApi->getOneByDevice($device);

        $temperature = null;
        $temperatureTimestamp = null;
        $temperatureStale = null;
        $temperatureMeasurement = $hasTemperature ? $deviceStatus->getTemperatureMeasurement() : null;
        if ($temperatureMeasurement instanceof DeviceStatusTemperatureMeasurementInterface) {
            $value = $temperatureMeasurement->getTemperature();
            $temperature = $value->getValue();
            $temperatureTimestamp = $value->getTimestamp();
            $temperatureStale = $temperatureTimestamp < $this->now - self::STALE_THRESHOLD;
        }

        $humidity = null;
        $humidityTimestamp = null;
        $humidityStale = null;
        $humidityMeasurement = $hasHumidity ? $deviceStatus->getRelativeHumidityMeasurement() : null;
        if ($humidityMeasurement instanceof DeviceStatusRelativeHumidityMeasurementInterface) {
            $value = $humidityMeasurement->getHumidity();
            $humidity = $value->getValue();
            $humidityTimestamp = $value->getTimestamp();
            $humidityStale = $humidityTimestamp < $this->now - self::STALE_THRESHOLD;
        }

        if (null === $temperature && null === $humidity) {
            return null;
        }

        return new DeviceReading(
            $device->getLabel(),
            $temperature,
            $temperatureTimestamp,
            $temperatureStale,
            $humidity,
            $humidityTimestamp,
            $humidityStale
        );
    }

    /**
     * @return array{0: bool, 1: bool} Whether the device supports [temperature, relative humidity] measurement
     */
    private function detectSupportedMeasurements(DeviceInterface $device): array
    {
        $hasTemperature = false;
        $hasHumidity = false;
        foreach ($device->getComponents() as $component) {
            if ($component instanceof DeviceComponentInterface) {
                foreach ($component->getCapabilities() as $capability) {
                    if ($capability instanceof DeviceComponentCapabilityInterface) {
                        if (self::ID_VALUE_TEMPERATURE_MEASUREMENT === $capability->getId()) {
                            $hasTemperature = true;
                        } elseif (self::ID_VALUE_RELATIVE_HUMIDITY_MEASUREMENT === $capability->getId()) {
                            $hasHumidity = true;
                        }
                    }
                }
            }
        }

        return [$hasTemperature, $hasHumidity];
    }

    private function processDevice(DeviceInterface $device): ?DeviceReading
    {
        if (!$device->getComponents()) {
            return null;
        }

        [$hasTemperature, $hasHumidity] = $this->detectSupportedMeasurements($device);
        if (!$hasTemperature && !$hasHumidity) {
            return null;
        }

        return $this->buildReading($device, $hasTemperature, $hasHumidity);
    }
}
