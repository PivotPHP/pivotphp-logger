<?php

declare(strict_types=1);

namespace PivotPHP\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for creating PSR-3 Logger instances.
 */
class LoggerFactory
{
    /**
     * Creates a new Logger instance based on configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): LoggerInterface
    {
        return new NullLogger();
    }
}
