<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\GcpFunction\ResponseInterface as FunctionResponseInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Schema(
    schema: 'ErrorEnvelope',
    description: 'The JSON error envelope returned for a failed request (an authorization failure or an unhandled error).',
    required: [
        FunctionResponseInterface::RESPONSE_API_KEY_SUCCESS,
        FunctionResponseInterface::RESPONSE_API_KEY_TIMESTAMP_UNIX,
        FunctionResponseInterface::RESPONSE_API_KEY_TIMESTAMP_ISO8601,
        FunctionResponseInterface::RESPONSE_API_KEY_VERSION,
        FunctionResponseInterface::RESPONSE_API_KEY_ERROR,
    ],
    properties: [
        new OA\Property(property: FunctionResponseInterface::RESPONSE_API_KEY_SUCCESS, type: 'boolean'),
        new OA\Property(property: FunctionResponseInterface::RESPONSE_API_KEY_TIMESTAMP_UNIX, type: 'integer'),
        new OA\Property(property: FunctionResponseInterface::RESPONSE_API_KEY_TIMESTAMP_ISO8601, type: 'string', format: 'date-time'),
        new OA\Property(property: FunctionResponseInterface::RESPONSE_API_KEY_VERSION, type: 'string'),
        new OA\Property(property: FunctionResponseInterface::RESPONSE_API_KEY_ERROR, type: 'string'),
    ],
    type: 'object',
    additionalProperties: false,
)]
interface RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
