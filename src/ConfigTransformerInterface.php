<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

interface ConfigTransformerInterface
{
    public const ENV_API_TOKEN = 'SMARTTHINGS_API_TOKEN';
    public const ENV_LOCATION_ID = 'SMARTTHINGS_LOCATION_ID';

    /**
     * @param mixed[] $env
     */
    public function transform(array $env): ConfigInterface;
}
