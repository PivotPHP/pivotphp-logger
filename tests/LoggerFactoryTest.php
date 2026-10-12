<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Tests;

use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use PivotPHP\Logger\Formatters\JsonLogFormatter;
use PivotPHP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class LoggerFactoryTest extends TestCase
{
    private string $tempLogFile;

    protected function setUp(): void
    {
        $this->tempLogFile = sys_get_temp_dir() . '/test_pivotphp_' . uniqid('', true) . '.log';
        unset($_ENV['LOG_CHANNEL'], $_ENV['LOG_LEVEL'], $_ENV['LOG_PATH']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            @unlink($this->tempLogFile);
        }
        foreach (glob(sys_get_temp_dir() . '/test_pivotphp_*.log') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function testCreateReturnsLoggerInterface(): void
    {
        $logger = LoggerFactory::create();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testSingleChannelWritesToFile(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'single',
            'channels' => [
                'single' => [
                    'driver' => 'single',
                    'path' => $this->tempLogFile,
                    'level' => 'debug',
                ],
            ],
        ]);

        $logger->info('Hello from single channel', ['app' => 'pivotphp']);

        $this->assertFileExists($this->tempLogFile);
        $content = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('INFO: Hello from single channel', $content);
        $this->assertStringContainsString('"app":"pivotphp"', $content);
    }

    public function testDailyChannelCreatesFile(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'daily',
            'channels' => [
                'daily' => [
                    'driver' => 'daily',
                    'path' => $this->tempLogFile,
                    'days' => 7,
                    'level' => 'info',
                ],
            ],
        ]);

        $logger->info('Hello daily rotation');

        // RotatingFileHandler produces: {filename}-{date}.log
        $pattern = sys_get_temp_dir() . '/' . pathinfo($this->tempLogFile, PATHINFO_FILENAME) . '-*.log';
        $rotatedFiles = glob($pattern) ?: [];
        $this->assertNotEmpty($rotatedFiles);

        $content = (string) file_get_contents($rotatedFiles[0]);
        $this->assertStringContainsString('Hello daily rotation', $content);
    }

    public function testStdoutChannelWithJsonFormatter(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'stdout',
            'channels' => [
                'stdout' => [
                    'driver' => 'stdout',
                    'stream' => $this->tempLogFile, // Use temp file to inspect stream write
                    'level' => 'info',
                    'formatter' => 'json',
                ],
            ],
        ]);

        $logger->warning('System alert', ['code' => 503]);

        $content = (string) file_get_contents($this->tempLogFile);
        $decoded = json_decode(trim($content), true);
        $this->assertIsArray($decoded);
        $this->assertSame('WARNING', $decoded['level']);
        $this->assertSame('System alert', $decoded['message']);
        $this->assertSame(503, $decoded['context']['code']);
    }

    public function testStderrChannel(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'stderr',
            'channels' => [
                'stderr' => [
                    'driver' => 'stderr',
                    'stream' => $this->tempLogFile,
                    'level' => 'error',
                ],
            ],
        ]);

        $logger->error('Fatal crash averted');

        $content = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('ERROR: Fatal crash averted', $content);
    }

    public function testNullChannelDiscardsLogs(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'null',
            'channels' => [
                'null' => [
                    'driver' => 'null',
                ],
            ],
        ]);

        $logger->emergency('Will not write anywhere');

        $this->assertFileDoesNotExist($this->tempLogFile);
    }

    public function testSyslogChannelInstantiation(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'syslog',
            'channels' => [
                'syslog' => [
                    'driver' => 'syslog',
                    'ident' => 'test-app',
                    'level' => 'notice',
                ],
            ],
        ]);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testStackChannelAggregatesHandlers(): void
    {
        $fileA = sys_get_temp_dir() . '/stack_a_' . uniqid() . '.log';
        $fileB = sys_get_temp_dir() . '/stack_b_' . uniqid() . '.log';

        $logger = LoggerFactory::create([
            'default' => 'stack',
            'channels' => [
                'stack' => [
                    'driver' => 'stack',
                    'channels' => ['chan_a', 'chan_b'],
                ],
                'chan_a' => [
                    'driver' => 'single',
                    'path' => $fileA,
                    'level' => 'debug',
                ],
                'chan_b' => [
                    'driver' => 'single',
                    'path' => $fileB,
                    'level' => 'debug',
                ],
            ],
        ]);

        $logger->info('Multi-cast message');

        $this->assertFileExists($fileA);
        $this->assertFileExists($fileB);
        $this->assertStringContainsString('Multi-cast message', (string) file_get_contents($fileA));
        $this->assertStringContainsString('Multi-cast message', (string) file_get_contents($fileB));

        @unlink($fileA);
        @unlink($fileB);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'single',
            'channels' => [
                'single' => [
                    'driver' => 'single',
                    'path' => $this->tempLogFile,
                    'level' => 'warning', // Filter out debug and info!
                ],
            ],
        ]);

        $logger->debug('Ignored debug');
        $logger->info('Ignored info');
        $logger->warning('Captured warning');
        $logger->error('Captured error');

        $content = (string) file_get_contents($this->tempLogFile);
        $this->assertStringNotContainsString('Ignored debug', $content);
        $this->assertStringNotContainsString('Ignored info', $content);
        $this->assertStringContainsString('Captured warning', $content);
        $this->assertStringContainsString('Captured error', $content);
    }

    public function testMaskSensitiveDataIntegratedByDefault(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'single',
            'channels' => [
                'single' => [
                    'driver' => 'single',
                    'path' => $this->tempLogFile,
                    'level' => 'debug',
                ],
            ],
        ]);

        $logger->info('Auth event', [
            'username' => 'admin',
            'password' => 'super_secret',
            'token' => 'jwt_secret',
        ]);

        $content = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('admin', $content);
        $this->assertStringNotContainsString('super_secret', $content);
        $this->assertStringNotContainsString('jwt_secret', $content);
        $this->assertStringContainsString('********', $content);
    }

    public function testMaskSensitiveDataCanBeDisabled(): void
    {
        $logger = LoggerFactory::create([
            'default' => 'single',
            'mask_sensitive' => false,
            'channels' => [
                'single' => [
                    'driver' => 'single',
                    'path' => $this->tempLogFile,
                    'level' => 'debug',
                ],
            ],
        ]);

        $logger->info('Debug raw', ['password' => 'visible_plain_password']);

        $content = (string) file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('visible_plain_password', $content);
    }

    public function testChannelMethod(): void
    {
        $logger = LoggerFactory::channel('custom', [
            'channels' => [
                'custom' => [
                    'driver' => 'single',
                    'path' => $this->tempLogFile,
                    'level' => 'notice',
                ],
            ],
        ]);

        $logger->notice('Custom channel message');

        $this->assertStringContainsString('Custom channel message', (string) file_get_contents($this->tempLogFile));
    }

    public function testInvalidChannelDriverThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported log channel driver: 'non_existent'");

        LoggerFactory::create([
            'default' => 'broken',
            'channels' => [
                'broken' => [
                    'driver' => 'non_existent',
                ],
            ],
        ]);
    }

    public function testParseLevel(): void
    {
        $this->assertSame(Level::Debug, LoggerFactory::parseLevel('debug'));
        $this->assertSame(Level::Info, LoggerFactory::parseLevel('INFO'));
        $this->assertSame(Level::Warning, LoggerFactory::parseLevel('warning'));
        $this->assertSame(Level::Error, LoggerFactory::parseLevel('error'));
        $this->assertSame(Level::Critical, LoggerFactory::parseLevel('critical'));
        $this->assertSame(Level::Alert, LoggerFactory::parseLevel('alert'));
        $this->assertSame(Level::Emergency, LoggerFactory::parseLevel('emergency'));
        $this->assertSame(Level::Debug, LoggerFactory::parseLevel('unknown_fallback'));
    }
}
