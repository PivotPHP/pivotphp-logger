<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Tests;

use PHPUnit\Framework\TestCase;
use PivotPHP\Logger\LoggerServiceProvider;
use Psr\Log\LoggerInterface;

class LoggerServiceProviderTest extends TestCase
{
    public function testRegistersLoggerIntoContainer(): void
    {
        $app = new class () {
            /** @var array<string, callable> */
            public array $singletons = [];

            /** @var array<string, string> */
            public array $aliases = [];

            public function singleton(string $abstract, callable $concrete): void
            {
                $this->singletons[$abstract] = $concrete;
            }

            public function alias(string $alias, string $abstract): void
            {
                $this->aliases[$alias] = $abstract;
            }

            public function getConfig(string $key, mixed $default = null): mixed
            {
                if ($key === 'logging') {
                    return [
                        'default' => 'null',
                        'channels' => [
                            'null' => ['driver' => 'null'],
                        ],
                    ];
                }
                return $default;
            }
        };

        $provider = new LoggerServiceProvider($app);
        $provider->register();

        $this->assertArrayHasKey(LoggerInterface::class, $app->singletons);
        $this->assertArrayHasKey('log', $app->aliases);
        $this->assertArrayHasKey('logger', $app->aliases);
        $this->assertSame(LoggerInterface::class, $app->aliases['log']);
        $this->assertSame(LoggerInterface::class, $app->aliases['logger']);

        $factoryClosure = $app->singletons[LoggerInterface::class];
        $instance = $factoryClosure();

        $this->assertInstanceOf(LoggerInterface::class, $instance);
    }

    public function testRegisterToStaticHelper(): void
    {
        $app = new class () {
            /** @var array<string, callable> */
            public array $singletons = [];

            public function singleton(string $abstract, callable $concrete): void
            {
                $this->singletons[$abstract] = $concrete;
            }

            public function alias(string $alias, string $abstract): void
            {
            }
        };

        LoggerServiceProvider::registerTo($app, [
            'default' => 'null',
            'channels' => ['null' => ['driver' => 'null']],
        ]);

        $this->assertArrayHasKey(LoggerInterface::class, $app->singletons);
        $instance = ($app->singletons[LoggerInterface::class])();
        $this->assertInstanceOf(LoggerInterface::class, $instance);
    }

    public function testProvidesList(): void
    {
        $dummyApp = new \stdClass();
        $provider = new LoggerServiceProvider($dummyApp);

        $this->assertSame(
            [LoggerInterface::class, 'log', 'logger'],
            $provider->provides()
        );
    }
}
