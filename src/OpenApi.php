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
                            new OA\Property(property: ResponseInterface::RESPONSE_API_KEY_DATA, ref: '#/components/schemas/ClimateData'),
                        ],
                    ),
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
