<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\CloudFunction\FunctionConfigInterface;

interface ConfigInterface
{
    public function getApiToken(): string;

    public function getFunctionConfig(): FunctionConfigInterface;

    public function getLocationId(): string;
}
