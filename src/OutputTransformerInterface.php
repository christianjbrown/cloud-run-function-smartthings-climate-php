<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ClimateData',
    description: 'The current climate readings, one entry per SmartThings device that exposes a temperature or relative-humidity measurement.',
    required: [
        self::KEY_DEVICES,
    ],
    properties: [
        new OA\Property(
            property: self::KEY_DEVICES,
            description: 'The per-device readings, sorted by device name.',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/DeviceReading'),
        ),
    ],
    type: 'object',
    additionalProperties: false,
)]
interface OutputTransformerInterface
{
    public const string KEY_DEVICES = 'devices';

    /**
     * @param DeviceReadingInterface[] $deviceTemperatures
     *
     * @return mixed[]
     */
    public function transform(array $deviceTemperatures): array;
}
