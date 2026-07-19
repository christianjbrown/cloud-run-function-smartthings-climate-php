<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

use ChristianBrown\ApiClient\ApiClient;
use ChristianBrown\GcpFunction\CloudFunction;
use ChristianBrown\GcpFunction\CloudFunctionInterface;
use ChristianBrown\GcpFunction\FunctionConfigTransformer;
use ChristianBrown\GcpFunction\JsonErrorResponse;
use ChristianBrown\GcpFunction\JsonErrorResponseInterface;
use ChristianBrown\GcpFunction\ResponseInterface as FunctionResponseInterface;
use ChristianBrown\KeyValueStore\DatabaseKeyValueStore;
use ChristianBrown\OAuth2Client\RefreshTokenManager;
use ChristianBrown\OAuth2Client\Transformer\AccessTokenTransformer;
use ChristianBrown\SmartThings\SmartThings;
use ChristianBrown\SmartThingsClimate\ConfigTransformer;
use ChristianBrown\SmartThingsClimate\Database\Entity\RefreshToken;
use ChristianBrown\SmartThingsClimate\Database\EntityManagerFactory;
use ChristianBrown\SmartThingsClimate\Database\MySqlAdvisoryLock;
use ChristianBrown\SmartThingsClimate\DataProvider;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformer;
use ChristianBrown\SmartThingsClimate\OutputTransformer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

const ACCESS_TOKEN_KEY = 'smartthings_access_token';
const REFRESH_TOKEN_KEY = 'smartthings_refresh_token';
const TOKEN_REFRESH_LOCK_NAME = 'smartthings_token_refresh';
const TOKEN_REFRESH_LOCK_TIMEOUT_SECONDS = 10;

function run(ServerRequestInterface $request): ResponseInterface
{
    $env = getenv();
    $functionConfigTransformer = new FunctionConfigTransformer();
    $configTransformer = new ConfigTransformer($functionConfigTransformer);
    $config = $configTransformer->transform($env);

    try {
        // Obtain a fresh SmartThings access token via the OAuth refresh-token flow.
        // Tokens are persisted in the shared Cloud SQL database, so the rotating
        // refresh token survives across invocations and instances.
        $entityManager = (new EntityManagerFactory($config->getDatabaseDsn()))->getEntityManager();
        $accessTokenKeyValueStore = new DatabaseKeyValueStore($entityManager, RefreshToken::class, ACCESS_TOKEN_KEY);
        $refreshTokenKeyValueStore = new DatabaseKeyValueStore($entityManager, RefreshToken::class, REFRESH_TOKEN_KEY);

        // Serialise the refresh with a database advisory lock (on the same
        // connection the token store uses) so that, if more than one instance ever
        // runs, a rotating refresh token is never spent by two refreshes at once.
        $refreshLock = new MySqlAdvisoryLock($entityManager->getConnection(), TOKEN_REFRESH_LOCK_NAME, TOKEN_REFRESH_LOCK_TIMEOUT_SECONDS);

        $jsonApiRequestSender = (new ApiClient())->getJsonApiRequestSender();
        $accessTokenTransformer = new AccessTokenTransformer();
        $refreshTokenManager = new RefreshTokenManager(
            $jsonApiRequestSender,
            $accessTokenKeyValueStore,
            $refreshTokenKeyValueStore,
            $accessTokenTransformer,
            $config->getTokenUrl(),
            $config->getClientSecret(),
            $refreshLock,
        );
        $accessToken = $refreshTokenManager->getAccessToken($config->getClientId());

        $smartThings = new SmartThings($accessToken->getAccessToken());
        $devicesApi = $smartThings->getDeviceApi();
        $devicesStatusApi = $smartThings->getDeviceStatusApi();
        $locationRoomApi = $smartThings->getLocationRoomApi();

        $deviceReadingOutputTransformer = new DeviceReadingOutputTransformer();
        $outputTransformer = new OutputTransformer($deviceReadingOutputTransformer);

        $dataProvider = new DataProvider($devicesApi, $devicesStatusApi, $locationRoomApi, $outputTransformer, $config->getLocationId());
        $cloudFunction = new CloudFunction($dataProvider, $config->getFunctionConfig());

        return $cloudFunction->run($request);
    } catch (Throwable $exception) {
        // Acquiring the OAuth token or building the SmartThings client happens
        // outside CloudFunction::run(), so a failure here (e.g. a revoked refresh
        // token returning invalid_grant) would otherwise escape as a bare 500.
        // Log the cause for Cloud Logging and return the framework's JSON error
        // envelope instead, keeping the response contract consistent — the CDN's
        // stale-if-error still shields visitors with the last good copy.
        error_log((string) $exception);
        $requestOrigin = $request->getHeaderLine(FunctionResponseInterface::HEADER_KEY_ORIGIN);

        return new JsonErrorResponse($config->getFunctionConfig(), CloudFunctionInterface::ERROR_UNHANDLED, JsonErrorResponseInterface::DEFAULT_ERROR_STATUS_CODE, $requestOrigin);
    }
}
