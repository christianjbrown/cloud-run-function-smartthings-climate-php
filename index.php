<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

use ChristianBrown\ApiClient\ApiClient;
use ChristianBrown\Database\ClimateMeasurementRecorder;
use ChristianBrown\Database\Entity\RefreshToken;
use ChristianBrown\Database\EntityManagerFactory;
use ChristianBrown\GcpFunction\CloudFunction;
use ChristianBrown\GcpFunction\CloudFunctionInterface;
use ChristianBrown\GcpFunction\FunctionConfigTransformer;
use ChristianBrown\KeyValueStore\DatabaseKeyValueStore;
use ChristianBrown\OAuth2Client\RefreshTokenManager;
use ChristianBrown\OAuth2Client\Transformer\AccessTokenTransformer;
use ChristianBrown\SmartThings\SmartThings;
use ChristianBrown\SmartThingsClimate\ClimateAverageCalculator;
use ChristianBrown\SmartThingsClimate\CloudFunctionFactoryInterface;
use ChristianBrown\SmartThingsClimate\ConfigInterface;
use ChristianBrown\SmartThingsClimate\ConfigTransformer;
use ChristianBrown\SmartThingsClimate\Database\MySqlAdvisoryLock;
use ChristianBrown\SmartThingsClimate\DataProvider;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformer;
use ChristianBrown\SmartThingsClimate\OutputTransformer;
use ChristianBrown\SmartThingsClimate\RequestHandler;
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

    // The OAuth token acquisition and SmartThings client construction happen inside
    // the factory (not here), so that RequestHandler::handle() wraps them in the same
    // try/catch as CloudFunction::run() and a failure there (e.g. a revoked refresh
    // token returning invalid_grant) returns the framework's JSON error envelope
    // rather than escaping as a bare 500.
    $cloudFunctionFactory = new class ($config) implements CloudFunctionFactoryInterface {
        private ConfigInterface $config;

        public function __construct(ConfigInterface $config)
        {
            $this->config = $config;
        }

        public function create(): CloudFunctionInterface
        {
            $config = $this->config;

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

            // Persist the average house temperature/humidity on the same entity
            // manager (and open connection) already used for the token store, so
            // the climate write reuses the existing connection. The write is
            // best-effort — DataProvider isolates it so a failure never disturbs
            // the response.
            $climateAverageCalculator = new ClimateAverageCalculator();
            $climateMeasurementRecorder = new ClimateMeasurementRecorder($entityManager);

            $dataProvider = new DataProvider($devicesApi, $devicesStatusApi, $locationRoomApi, $outputTransformer, $climateAverageCalculator, $climateMeasurementRecorder, $config->getLocationId());

            return new CloudFunction($dataProvider, $config->getFunctionConfig());
        }
    };

    $requestHandler = new RequestHandler($cloudFunctionFactory, $config->getFunctionConfig());

    return $requestHandler->handle($request);
}
