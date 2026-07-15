<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

use ChristianBrown\CloudFunction\CloudFunction;
use ChristianBrown\CloudFunction\FunctionConfigTransformer;
use ChristianBrown\GetSmartHomeTemps\ConfigTransformer;
use ChristianBrown\GetSmartHomeTemps\DataProvider;
use ChristianBrown\GetSmartHomeTemps\DeviceReadingOutputTransformer;
use ChristianBrown\GetSmartHomeTemps\OutputTransformer;
use ChristianBrown\SmartThings\SmartThings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

function run(ServerRequestInterface $request): ResponseInterface
{
    $env = getenv();
    $functionConfigTransformer = new FunctionConfigTransformer();
    $configTransformer = new ConfigTransformer($functionConfigTransformer);
    $config = $configTransformer->transform($env);

    $smartThings = new SmartThings($config->getApiToken());
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
