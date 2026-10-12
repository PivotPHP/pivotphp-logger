<?php

declare(strict_types=1);

namespace PivotPHP\Logger;

use Psr\Log\LoggerInterface;

/**
 * Service Provider to integrate PivotPHP Logger into PivotPHP Application Container.
 */
class LoggerServiceProvider
{
    /**
     * @var object The Application or Container instance
     */
    protected object $app;

    /**
     * @var array<string, mixed> Optional configuration override
     */
    protected array $config;

    /**
     * @param object $app Application or Container instance
     * @param array<string, mixed> $config
     */
    public function __construct(object $app, array $config = [])
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Registers LoggerInterface and its aliases into the application container.
     */
    public function register(): void
    {
        $config = $this->resolveConfig();

        if (method_exists($this->app, 'singleton')) {
            $this->app->singleton(
                LoggerInterface::class,
                static function () use ($config): LoggerInterface {
                    return LoggerFactory::create($config);
                }
            );
        }

        if (method_exists($this->app, 'alias')) {
            $this->app->alias('log', LoggerInterface::class);
            $this->app->alias('logger', LoggerInterface::class);
        }
    }

    /**
     * Convenient static helper to register into an application instance.
     *
     * @param object $app
     * @param array<string, mixed> $config
     */
    public static function registerTo(object $app, array $config = []): void
    {
        $provider = new self($app, $config);
        $provider->register();
    }

    /**
     * Resolves logging configuration from explicit config, app getConfig() or environment.
     *
     * @return array<string, mixed>
     */
    protected function resolveConfig(): array
    {
        if (!empty($this->config)) {
            return $this->config;
        }

        if (method_exists($this->app, 'getConfig')) {
            /** @var mixed $appConfig */
            $appConfig = $this->app->getConfig('logging', []);
            if (is_array($appConfig) && !empty($appConfig)) {
                /** @var array<string, mixed> $appConfig */
                return $appConfig;
            }
        }

        return [];
    }

    /**
     * Get the services provided by this provider.
     *
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            LoggerInterface::class,
            'log',
            'logger',
        ];
    }
}
