<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

use ChristianBrown\ApiClient\ApiClient;
use ChristianBrown\GcpFunction\CloudFunction;
use ChristianBrown\GcpFunction\FunctionConfigTransformer;
use ChristianBrown\KeyValueStore\DatabaseKeyValueStore;
use ChristianBrown\OAuth2Client\RefreshTokenManager;
use ChristianBrown\OAuth2Client\Transformer\AccessTokenTransformer;
use ChristianBrown\SmartThings\SmartThings;
use ChristianBrown\SmartThingsClimate\ConfigTransformer;
use ChristianBrown\SmartThingsClimate\Database\Entity\RefreshToken;
use ChristianBrown\SmartThingsClimate\Database\EntityManagerFactory;
use ChristianBrown\SmartThingsClimate\DataProvider;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformer;
use ChristianBrown\SmartThingsClimate\OutputTransformer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

const ACCESS_TOKEN_KEY = 'smartthings_access_token';
const REFRESH_TOKEN_KEY = 'smartthings_refresh_token';

function run(ServerRequestInterface $request): ResponseInterface
{
    $env = getenv();
    $functionConfigTransformer = new FunctionConfigTransformer();
    $configTransformer = new ConfigTransformer($functionConfigTransformer);
    $config = $configTransformer->transform($env);

    // Obtain a fresh SmartThings access token via the OAuth refresh-token flow.
    // Tokens are persisted in the shared Cloud SQL database, so the rotating
    // refresh token survives across invocations and instances.
    $entityManager = (new EntityManagerFactory($config->getDatabaseDsn()))->getEntityManager();
    $accessTokenKeyValueStore = new DatabaseKeyValueStore($entityManager, RefreshToken::class, ACCESS_TOKEN_KEY);
    $refreshTokenKeyValueStore = new DatabaseKeyValueStore($entityManager, RefreshToken::class, REFRESH_TOKEN_KEY);

    $jsonApiRequestSender = (new ApiClient())->getJsonApiRequestSender();
    $accessTokenTransformer = new AccessTokenTransformer();
    $refreshTokenManager = new RefreshTokenManager(
        $jsonApiRequestSender,
        $accessTokenKeyValueStore,
        $refreshTokenKeyValueStore,
        $accessTokenTransformer,
        $config->getTokenUrl(),
        $config->getClientSecret(),
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
    $response = $cloudFunction->run($request);

    return $response;
}
