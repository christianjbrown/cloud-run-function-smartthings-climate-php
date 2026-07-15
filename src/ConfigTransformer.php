<?php

declare(strict_types=1);

namespace ChristianBrown\GetSmartHomeTemps;

use ChristianBrown\CloudFunction\FunctionConfigTransformerInterface;
use RuntimeException;

final class ConfigTransformer implements ConfigTransformerInterface
{
    private FunctionConfigTransformerInterface $functionConfigTransformer;

    public function __construct(FunctionConfigTransformerInterface $functionConfigTransformer)
    {
        $this->functionConfigTransformer = $functionConfigTransformer;
    }

    /**
     * @param mixed[] $env
     */
    public function transform(array $env): ConfigInterface
    {
        if (empty($env[self::ENV_API_TOKEN]) || !is_string($env[self::ENV_API_TOKEN])) {
            throw new RuntimeException(sprintf('%s not set or not a string', self::ENV_API_TOKEN));
        }
        $apiToken = $env[self::ENV_API_TOKEN];

        $requestConfig = $this->functionConfigTransformer->transform($env);

        return new Config($requestConfig, $apiToken);
    }
}
