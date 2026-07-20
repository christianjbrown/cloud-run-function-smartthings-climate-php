<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate\Tests;

use ChristianBrown\GcpFunction\CloudFunction;
use ChristianBrown\GcpFunction\DataProviderInterface as BaseDataProviderInterface;
use ChristianBrown\GcpFunction\FunctionConfig;
use ChristianBrown\GcpFunction\FunctionConfigInterface;
use ChristianBrown\SmartThingsClimate\CloudFunctionFactoryInterface;
use ChristianBrown\SmartThingsClimate\DeviceReadingInterface;
use ChristianBrown\SmartThingsClimate\DeviceReadingOutputTransformer;
use ChristianBrown\SmartThingsClimate\MeasurementInterface;
use ChristianBrown\SmartThingsClimate\OutputTransformer;
use ChristianBrown\SmartThingsClimate\RequestHandler;
use GuzzleHttp\Psr7\ServerRequest;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function dirname;
use function ini_set;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Validates the function's real PSR-7 responses against the committed
 * `openapi.yaml` (generated from the `#[OA\...]` attributes). If a response ever
 * drifts from the contract — an unexpected key, a wrong type, a missing required
 * field — `ResponseValidator::validate()` throws and the suite fails.
 */
#[CoversClass(RequestHandler::class)]
#[UsesClass(OutputTransformer::class)]
#[UsesClass(DeviceReadingOutputTransformer::class)]
final class ContractTest extends TestCase
{
    private const string ORIGIN = 'https://example.com';
    private const string REVISION = 'contract-test-revision';
    private const int TIME = 1752580800;
    private ResponseValidator $responseValidator;

    protected function setUp(): void
    {
        $this->responseValidator = (new ValidatorBuilder())
            ->fromYamlFile(dirname(__DIR__).'/openapi.yaml')
            ->getResponseValidator();
    }

    /**
     * @throws Exception
     */
    public function testDataProviderFailureErrorResponseMatchesContract(): void
    {
        // An upstream failure inside CloudFunction::run() (e.g. the SmartThings API
        // erroring) surfaces from getData() and is caught as an unhandled 500.
        $dataProvider = self::createStub(BaseDataProviderInterface::class);
        $dataProvider->method('getData')
            ->willThrowException(new RuntimeException('SmartThings API failure'));

        $response = $this->buildResponse($this->allowedConfig(), $dataProvider, new ServerRequest('GET', '/'));

        $this->responseValidator->validate(new OperationAddress('/', 'get'), $response);
        self::assertSame(500, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testFactoryFailureErrorResponseMatchesContract(): void
    {
        // A revoked refresh token (invalid_grant) surfaces from the factory's token
        // acquisition, before the CloudFunction exists — the handler converts it into
        // the framework's JSON error envelope, not a bare 500.
        $cloudFunctionFactory = self::createStub(CloudFunctionFactoryInterface::class);
        $cloudFunctionFactory->method('create')
            ->willThrowException(new RuntimeException('invalid_grant'));

        $requestHandler = new RequestHandler($cloudFunctionFactory, $this->allowedConfig());

        // The handler logs the cause via error_log() for Cloud Logging; divert it to a
        // temp file so the strict-output check does not see it as unexpected output.
        $errorLog = (string) tempnam(sys_get_temp_dir(), 'contract-test');
        $previousErrorLog = (string) ini_set('error_log', $errorLog);

        try {
            $response = $requestHandler->handle(new ServerRequest('GET', '/'));
        } finally {
            ini_set('error_log', $previousErrorLog);
            unlink($errorLog);
        }

        $this->responseValidator->validate(new OperationAddress('/', 'get'), $response);
        self::assertSame(500, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testSuccessEmptyPayloadMatchesContract(): void
    {
        $data = (new OutputTransformer(new DeviceReadingOutputTransformer()))->transform([]);

        $dataProvider = self::createStub(BaseDataProviderInterface::class);
        $dataProvider->method('getData')
            ->willReturn($data);

        $response = $this->buildResponse($this->allowedConfig(), $dataProvider, new ServerRequest('GET', '/'));

        $this->responseValidator->validate(new OperationAddress('/', 'get'), $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testSuccessFullPayloadMatchesContract(): void
    {
        // A whole-valued temperature (21.0) is serialised as `21`, proving the schema's
        // `number` type accepts an integer JSON token; humidity (55.5) is fractional.
        $readings = [
            $this->createReading('Hallway', 'Hallway', 21.0, self::TIME, false, 55.5, self::TIME, false),
            $this->createReading('Loft', null, 17.3, self::TIME, true, null, null, null),
            $this->createReading('Bathroom', 'Bathroom', null, null, null, 60.0, self::TIME, true),
        ];

        $data = (new OutputTransformer(new DeviceReadingOutputTransformer()))->transform($readings);

        $dataProvider = self::createStub(BaseDataProviderInterface::class);
        $dataProvider->method('getData')
            ->willReturn($data);

        $request = new ServerRequest('GET', '/', ['Origin' => self::ORIGIN]);
        $response = $this->buildResponse($this->allowedConfig(), $dataProvider, $request);

        $this->responseValidator->validate(new OperationAddress('/', 'get'), $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testSuccessMinimalPayloadMatchesContract(): void
    {
        $readings = [$this->createReading('Landing', null, null, null, null, null, null, null)];

        $data = (new OutputTransformer(new DeviceReadingOutputTransformer()))->transform($readings);

        $dataProvider = self::createStub(BaseDataProviderInterface::class);
        $dataProvider->method('getData')
            ->willReturn($data);

        $response = $this->buildResponse($this->allowedConfig(), $dataProvider, new ServerRequest('GET', '/'));

        $this->responseValidator->validate(new OperationAddress('/', 'get'), $response);
        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @throws Exception
     */
    public function testUnauthorizedResponseMatchesContract(): void
    {
        $config = (new FunctionConfig(self::REVISION))
            ->setRequiredHeaderKey('X-Request-Auth')
            ->setRequiredHeaderValue('secret');

        $dataProvider = self::createStub(BaseDataProviderInterface::class);

        $response = $this->buildResponse($config, $dataProvider, new ServerRequest('GET', '/'));

        $this->responseValidator->validate(new OperationAddress('/', 'get'), $response);
        self::assertSame(401, $response->getStatusCode());
    }

    private function allowedConfig(): FunctionConfigInterface
    {
        return (new FunctionConfig(self::REVISION))
            ->setAllowUnauthenticated(true)
            ->setRequiredOrigin(self::ORIGIN)
            ->setUseCacheTtl(300)
            ->setUseCacheButRequestTtl(600)
            ->setUseCacheIfErrorTtl(3600);
    }

    /**
     * @throws Exception
     */
    private function buildResponse(FunctionConfigInterface $config, BaseDataProviderInterface $dataProvider, ServerRequestInterface $request): ResponseInterface
    {
        $cloudFunction = new CloudFunction($dataProvider, $config);

        $cloudFunctionFactory = self::createStub(CloudFunctionFactoryInterface::class);
        $cloudFunctionFactory->method('create')
            ->willReturn($cloudFunction);

        $requestHandler = new RequestHandler($cloudFunctionFactory, $config);

        return $requestHandler->handle($request);
    }

    /**
     * @throws Exception
     */
    private function createMeasurement(?float $value, ?int $timestamp, ?bool $stale): MeasurementInterface
    {
        $measurement = self::createStub(MeasurementInterface::class);
        $measurement->method('getValue')
            ->willReturn($value);
        $measurement->method('getTimestamp')
            ->willReturn($timestamp);
        $measurement->method('isStale')
            ->willReturn($stale);

        return $measurement;
    }

    /**
     * @throws Exception
     */
    private function createReading(string $name, ?string $roomName, ?float $temperatureValue, ?int $temperatureTimestamp, ?bool $temperatureStale, ?float $humidityValue, ?int $humidityTimestamp, ?bool $humidityStale): DeviceReadingInterface
    {
        $temperature = null === $temperatureValue ? null : $this->createMeasurement($temperatureValue, $temperatureTimestamp, $temperatureStale);
        $humidity = null === $humidityValue ? null : $this->createMeasurement($humidityValue, $humidityTimestamp, $humidityStale);

        $reading = self::createStub(DeviceReadingInterface::class);
        $reading->method('getName')
            ->willReturn($name);
        $reading->method('getRoomName')
            ->willReturn($roomName);
        $reading->method('getTemperature')
            ->willReturn($temperature);
        $reading->method('getHumidity')
            ->willReturn($humidity);

        return $reading;
    }
}
