<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\CloudFunction\FunctionConfigInterface;

final class Config implements ConfigInterface
{
    private string $apiToken;
    private FunctionConfigInterface $functionConfig;
    private string $locationId;

    public function __construct(FunctionConfigInterface $functionConfig, string $apiToken, string $locationId)
    {
        $this->functionConfig = $functionConfig;
        $this->apiToken = $apiToken;
        $this->locationId = $locationId;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function getFunctionConfig(): FunctionConfigInterface
    {
        return $this->functionConfig;
    }

    public function getLocationId(): string
    {
        return $this->locationId;
    }
}
