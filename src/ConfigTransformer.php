<?php

declare(strict_types=1);

namespace ChristianBrown\SmartThingsClimate;

use ChristianBrown\GcpFunction\FunctionConfigTransformerInterface;
use RuntimeException;

use function is_string;
use function sprintf;

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
        $clientId = $this->extractRequiredString($env, self::ENV_CLIENT_ID);
        $clientSecret = $this->extractRequiredString($env, self::ENV_CLIENT_SECRET);
        $databaseDsn = $this->extractRequiredString($env, self::ENV_DATABASE_DSN);
        $locationId = $this->extractRequiredString($env, self::ENV_LOCATION_ID);
        $tokenUrl = $this->extractRequiredString($env, self::ENV_TOKEN_URL);

        $requestConfig = $this->functionConfigTransformer->transform($env);

        return new Config($requestConfig, $clientId, $clientSecret, $databaseDsn, $locationId, $tokenUrl);
    }

    /**
     * @param mixed[] $env
     */
    private function extractRequiredString(array $env, string $key): string
    {
        // Split into sequential guards (rather than a single `||`) so each
        // failure path is independently reachable for path coverage.
        if (empty($env[$key])) {
            throw new RuntimeException(sprintf('%s not set or not a string', $key));
        }
        if (!is_string($env[$key])) {
            throw new RuntimeException(sprintf('%s not set or not a string', $key));
        }

        return $env[$key];
    }
}
