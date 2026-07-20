<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'DeviceReading',
    description: 'One device\'s readings. The device name is always present; the room name and each measurement block (value/timestamp/stale) appear only when that value is available. A whole-valued measurement is serialised without a decimal point, so its type is `number`, not `integer`.',
    required: [
        self::KEY_NAME,
    ],
    properties: [
        new OA\Property(property: self::KEY_NAME, description: 'The device label.', type: 'string'),
        new OA\Property(property: self::KEY_ROOM_NAME, description: 'The name of the room the device is in.', type: 'string'),
        new OA\Property(property: self::KEY_TEMPERATURE_VALUE, description: 'The temperature reading, in the device\'s reported unit.', type: 'number'),
        new OA\Property(property: self::KEY_TEMPERATURE_TIMESTAMP, description: 'When the temperature was read (Unix seconds).', type: 'integer'),
        new OA\Property(property: self::KEY_TEMPERATURE_STALE, description: 'Whether the temperature reading is older than the stale threshold.', type: 'boolean'),
        new OA\Property(property: self::KEY_HUMIDITY_VALUE, description: 'The relative humidity reading (percent).', type: 'number'),
        new OA\Property(property: self::KEY_HUMIDITY_TIMESTAMP, description: 'When the humidity was read (Unix seconds).', type: 'integer'),
        new OA\Property(property: self::KEY_HUMIDITY_STALE, description: 'Whether the humidity reading is older than the stale threshold.', type: 'boolean'),
    ],
    type: 'object',
    additionalProperties: false,
)]
interface DeviceReadingOutputTransformerInterface
{
    public const string KEY_HUMIDITY_STALE = 'humidityStale';
    public const string KEY_HUMIDITY_TIMESTAMP = 'humidityTimestamp';
    public const string KEY_HUMIDITY_VALUE = 'humidityValue';
    public const string KEY_NAME = 'name';
    public const string KEY_ROOM_NAME = 'roomName';
    public const string KEY_TEMPERATURE_STALE = 'temperatureStale';
    public const string KEY_TEMPERATURE_TIMESTAMP = 'temperatureTimestamp';
    public const string KEY_TEMPERATURE_VALUE = 'temperatureValue';

    /**
     * @return mixed[]
     */
    public function transform(DeviceReadingInterface $deviceReading): array;
}
