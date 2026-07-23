<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\GcpFunction\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * Inert OpenAPI spec-holder.
 *
 * This class carries no runtime behaviour and is never instantiated: it exists
 * only so `zircote/swagger-php` can scan its `#[OA\...]` attributes and emit the
 * committed `openapi.yaml`. Keeping the top-level `#[OA\Info]`/`#[OA\Server]` and
 * the `#[OA\Get]` operation here means the HTTP contract is generated from the same
 * typed code that produces the responses, so it cannot silently drift. The success
 * response composes the shared `SuccessEnvelope` (declared in `php-gcp-function-lib`)
 * with this function's `ClimateData` schema via `allOf`, narrowing only the generic
 * `data` placeholder; the error responses reference the shared `ErrorEnvelope`. It
 * has no executable lines and is excluded from coverage in `phpunit.xml`, like a
 * config file.
 */
#[OA\Info(
    version: '1.0.0',
    description: 'Reads the current temperature and relative humidity from the SmartThings devices in a fixed location and returns them as a single JSON envelope.',
    title: 'SmartThings Climate Cloud Function',
)]
#[OA\Server(url: '/')]
#[OA\Get(
    path: '/',
    operationId: 'getClimate',
    summary: 'Get the current climate readings for the configured location.',
    description: 'Returns the current temperature and relative humidity readings for the SmartThings devices in the function\'s configured location.',
    responses: [
        new OA\Response(
            response: ResponseInterface::STATUS_OK,
            description: 'The current climate readings for the configured location.',
            headers: [
                new OA\Header(header: ResponseInterface::HEADER_KEY_ALLOW_METHODS, description: 'Allowed CORS methods.', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_ALLOW_ORIGIN, description: 'Configured allowed origin (present when a required origin is configured).', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_CACHE_CONTROL, description: 'CDN/browser cache directives (present when cache TTLs are configured).', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_SURROGATE_CONTROL, description: 'Surrogate cache directives (present when cache TTLs are configured).', schema: new OA\Schema(type: 'string')),
                new OA\Header(header: ResponseInterface::HEADER_KEY_VARY, description: 'Vary list (present when a required origin is configured).', schema: new OA\Schema(type: 'string')),
            ],
            content: new OA\JsonContent(
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/SuccessEnvelope'),
                    new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: ResponseInterface::RESPONSE_API_KEY_DATA,
                                description: 'The current climate readings, one entry per SmartThings device that exposes a temperature or relative-humidity measurement, sorted by device name.',
                                type: 'array',
                                items: new OA\Items(ref: '#/components/schemas/DeviceReading'),
                            ),
                        ],
                    ),
                ],
                // A real 200 body captured from a live request, with the device/room
                // labels genericised (this is a public repo); the numeric readings and
                // timestamps are exactly as returned. Keys come from the same constants
                // the response is built from, so the sample cannot drift from the schema.
                example: [
                    ResponseInterface::RESPONSE_API_KEY_DATA => [
                        [
                            DeviceReadingOutputTransformerInterface::KEY_NAME => 'Button',
                            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'Living Room',
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 25.1,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 1784571323,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                        ],
                        [
                            DeviceReadingOutputTransformerInterface::KEY_NAME => 'Button',
                            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'Office',
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 24.8,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 1784567960,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                        ],
                        [
                            DeviceReadingOutputTransformerInterface::KEY_NAME => 'Door sensor',
                            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'Hallway',
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 22.3,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 1784571388,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                        ],
                        [
                            DeviceReadingOutputTransformerInterface::KEY_NAME => 'Hygrometer',
                            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'Bedroom',
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 24.5,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 1784566164,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_VALUE => 43,
                            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_TIMESTAMP => 1784570405,
                            DeviceReadingOutputTransformerInterface::KEY_HUMIDITY_STALE => false,
                        ],
                        [
                            DeviceReadingOutputTransformerInterface::KEY_NAME => 'Motion sensor',
                            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'Hallway',
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 25.9,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 1784571267,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                        ],
                        [
                            DeviceReadingOutputTransformerInterface::KEY_NAME => 'Motion sensor',
                            DeviceReadingOutputTransformerInterface::KEY_ROOM_NAME => 'Living Room',
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_VALUE => 25.1,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_TIMESTAMP => 1784570955,
                            DeviceReadingOutputTransformerInterface::KEY_TEMPERATURE_STALE => false,
                        ],
                    ],
                    ResponseInterface::RESPONSE_API_KEY_SUCCESS => true,
                    ResponseInterface::RESPONSE_API_KEY_TIMESTAMP_ISO8601 => '2026-07-20T18:19:48+00:00',
                    ResponseInterface::RESPONSE_API_KEY_TIMESTAMP_UNIX => 1784571588,
                    ResponseInterface::RESPONSE_API_KEY_VERSION => 'get-smartthings-climate-00058-gxq',
                ],
            ),
        ),
        new OA\Response(
            response: ResponseInterface::STATUS_UNAUTHORIZED,
            description: 'The request failed header authorization.',
            content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
        ),
        new OA\Response(
            response: ResponseInterface::STATUS_INTERNAL_SERVER_ERROR,
            description: 'An unhandled error occurred: the OAuth refresh-token flow failed (e.g. a revoked refresh token returning invalid_grant), the token store could not be reached, the upstream SmartThings API failed, or the response could not be encoded.',
            content: new OA\JsonContent(ref: '#/components/schemas/ErrorEnvelope'),
        ),
    ],
)]
final class OpenApi
{
}
