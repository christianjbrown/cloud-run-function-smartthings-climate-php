<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

use ChristianBrown\ApiClient\ApiClientFactory;
use ChristianBrown\ApiClient\ClientOptions;
use ChristianBrown\Database\ClimateMeasurementRecorder;
use ChristianBrown\Database\Entity\RefreshToken;
use ChristianBrown\Database\EntityManagerFactory;
use ChristianBrown\CloudRunFunction\AllowOriginResolver;
use ChristianBrown\CloudRunFunction\CacheHeaderBuilder;
use ChristianBrown\CloudRunFunction\CloudRunFunctionFactory;
use ChristianBrown\CloudRunFunction\CloudRunFunctionFactoryInterface as LibraryCloudRunFunctionFactoryInterface;
use ChristianBrown\CloudRunFunction\CloudRunFunctionInterface;
use ChristianBrown\CloudRunFunction\CorsHeaderBuilder;
use ChristianBrown\CloudRunFunction\JsonResponseFactory;
use ChristianBrown\CloudRunFunction\ResponseBodyBuilder;
use ChristianBrown\KeyValueStore\DatabaseKeyValueStore;
use ChristianBrown\OAuth2Client\Authentication\ClientSecretBasicAuthentication;
use ChristianBrown\OAuth2Client\RefreshTokenManagerFactory;
use ChristianBrown\SmartThings\SmartThingsFactory;
use ChristianBrown\SmartThingsClimate\ClimateAverageCalculator;
use ChristianBrown\SmartThingsClimate\ClimateRecorder;
use ChristianBrown\SmartThingsClimate\CloudRunFunctionFactoryInterface;
use ChristianBrown\SmartThingsClimate\ConfigInterface;
use ChristianBrown\SmartThingsClimate\ConfigTransformer;
use ChristianBrown\SmartThingsClimate\Database\MySqlAdvisoryLock;
use ChristianBrown\SmartThingsClimate\DataProvider;
use ChristianBrown\SmartThingsClimate\DeviceFetcher;
use ChristianBrown\SmartThingsClimate\DeviceReadingBuilder;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformer;
use ChristianBrown\SmartThingsClimate\MeasurementCapabilityDetector;
use ChristianBrown\SmartThingsClimate\OutputTransformer;
use ChristianBrown\SmartThingsClimate\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Clock\NativeClock;

const ACCESS_TOKEN_KEY = 'smartthings_access_token';
const REFRESH_TOKEN_KEY = 'smartthings_refresh_token';
const TOKEN_REFRESH_LOCK_NAME = 'smartthings_token_refresh';
const TOKEN_REFRESH_LOCK_TIMEOUT_SECONDS = 10;

function run(ServerRequestInterface $request): ResponseInterface
{
    $env = getenv();
    $libraryFactory = new CloudRunFunctionFactory();
    $functionConfigTransformer = $libraryFactory->createConfigTransformer();
    $configTransformer = new ConfigTransformer($functionConfigTransformer);
    $config = $configTransformer->transform($env);

    // The OAuth token acquisition and SmartThings client construction happen inside
    // the factory (not here), so that RequestHandler::handle() wraps them in the same
    // try/catch as CloudRunFunction::run() and a failure there (e.g. a revoked refresh
    // token returning invalid_grant) returns the framework's JSON error envelope
    // rather than escaping as a bare 500.
    $cloudFunctionFactory = new class ($config, $libraryFactory) implements CloudRunFunctionFactoryInterface {
        private ConfigInterface $config;
        private LibraryCloudRunFunctionFactoryInterface $libraryFactory;

        public function __construct(ConfigInterface $config, LibraryCloudRunFunctionFactoryInterface $libraryFactory)
        {
            $this->config = $config;
            $this->libraryFactory = $libraryFactory;
        }

        public function create(): CloudRunFunctionInterface
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

            $clock = new NativeClock();
            $jsonApiRequestSender = (new ApiClientFactory(new ClientOptions()))->create()->getJsonApiRequestSender();
            $refreshTokenManager = (new RefreshTokenManagerFactory($clock))->create(
                $jsonApiRequestSender,
                $accessTokenKeyValueStore,
                $refreshTokenKeyValueStore,
                $config->getTokenUrl(),
                new ClientSecretBasicAuthentication($config->getClientSecret()),
                $refreshLock,
            );
            $accessToken = $refreshTokenManager->getAccessToken($config->getClientId());

            $smartThings = (new SmartThingsFactory())->create($accessToken->getAccessToken());
            $devicesApi = $smartThings->getDeviceApi();
            $devicesStatusApi = $smartThings->getDeviceStatusApi();
            $locationRoomApi = $smartThings->getLocationRoomApi();

            $deviceReadingOutputTransformer = new DeviceReadingOutputTransformer();
            $outputTransformer = new OutputTransformer($deviceReadingOutputTransformer);

            // Persist the average house temperature/humidity on the same entity
            // manager (and open connection) already used for the token store, so
            // the climate write reuses the existing connection. The write is
            // best-effort — ClimateRecorder isolates it so a failure never disturbs
            // the response.
            $climateAverageCalculator = new ClimateAverageCalculator();
            $climateMeasurementRecorder = new ClimateMeasurementRecorder($entityManager);
            $climateRecorder = new ClimateRecorder($climateAverageCalculator, $climateMeasurementRecorder, $clock);

            $deviceFetcher = new DeviceFetcher($devicesApi, $config->getLocationId());
            $measurementCapabilityDetector = new MeasurementCapabilityDetector();
            $deviceReadingBuilder = new DeviceReadingBuilder($devicesStatusApi, $locationRoomApi, $clock);

            $dataProvider = new DataProvider($deviceFetcher, $measurementCapabilityDetector, $deviceReadingBuilder, $climateRecorder, $outputTransformer);

            return $this->libraryFactory->create($dataProvider, $config->getFunctionConfig());
        }
    };

    $requestHandler = new RequestHandler(
        $cloudFunctionFactory,
        $config->getFunctionConfig(),
        new JsonResponseFactory(new ResponseBodyBuilder(), new CorsHeaderBuilder(new AllowOriginResolver()), new CacheHeaderBuilder(), new NativeClock())
    );

    return $requestHandler->handle($request);
}
