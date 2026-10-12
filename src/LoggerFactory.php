<?php

declare(strict_types=1);

namespace PivotPHP\Logger;

use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\GroupHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Level;
use Monolog\Logger;
use PivotPHP\Logger\Formatters\JsonLogFormatter;
use PivotPHP\Logger\Processors\MaskSensitiveDataProcessor;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating PSR-3 compliant Monolog logger instances with multi-channel support.
 */
class LoggerFactory
{
    public const DEFAULT_CHANNEL_NAME = 'pivotphp';
    public const DEFAULT_LOG_FILENAME = 'pivotphp.log';

    /**
     * Creates a new LoggerInterface instance based on configuration and environment variables.
     *
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): LoggerInterface
    {
        $channelName = (string) ($config['name'] ?? self::DEFAULT_CHANNEL_NAME);
        $defaultChannel = (string) ($config['default'] ?? ($_ENV['LOG_CHANNEL'] ?? 'single'));

        return self::channel($defaultChannel, $config, $channelName);
    }

    /**
     * Creates a logger configured for a specific channel name.
     *
     * @param string $channel
     * @param array<string, mixed> $config
     * @param string|null $loggerName
     */
    public static function channel(string $channel, array $config = [], ?string $loggerName = null): LoggerInterface
    {
        $name = $loggerName ?? (string) ($config['name'] ?? self::DEFAULT_CHANNEL_NAME);
        $logger = new Logger($name);

        $handler = self::resolveHandler($channel, $config);
        $logger->pushHandler($handler);

        // Apply sensitive data masking processor by default
        $maskSensitive = $config['mask_sensitive'] ?? true;
        if ($maskSensitive !== false) {
            $sensitiveKeys = isset($config['sensitive_keys']) && is_array($config['sensitive_keys'])
                /** @var list<string> $keys */
                ? array_values((array) $config['sensitive_keys'])
                : null;

            $processor = $sensitiveKeys !== null
                ? new MaskSensitiveDataProcessor($sensitiveKeys)
                : new MaskSensitiveDataProcessor();

            $logger->pushProcessor($processor);
        }

        return $logger;
    }

    /**
     * Resolves and builds a Monolog HandlerInterface for a given channel name.
     *
     * @param string $channel
     * @param array<string, mixed> $config
     */
    public static function resolveHandler(string $channel, array $config): HandlerInterface
    {
        /** @var array<string, mixed> $channelConfig */
        $channelConfig = $config['channels'][$channel] ?? ['driver' => $channel];
        $driver = (string) ($channelConfig['driver'] ?? $channel);

        $fallbackLevel = (string) ($config['level'] ?? ($_ENV['LOG_LEVEL'] ?? 'debug'));
        $levelStr = (string) ($channelConfig['level'] ?? $fallbackLevel);
        $level = self::parseLevel($levelStr);
        $bubble = (bool) ($channelConfig['bubble'] ?? true);

        $formatter = self::resolveFormatter($channelConfig['formatter'] ?? 'line');

        switch ($driver) {
            case 'single':
                $defaultPath = sys_get_temp_dir() . '/' . self::DEFAULT_LOG_FILENAME;
                $path = (string) ($channelConfig['path'] ?? ($_ENV['LOG_PATH'] ?? $defaultPath));
                $handler = new StreamHandler($path, $level, $bubble);
                $handler->setFormatter($formatter);
                return $handler;

            case 'daily':
                $defaultPath = sys_get_temp_dir() . '/' . self::DEFAULT_LOG_FILENAME;
                $path = (string) ($channelConfig['path'] ?? ($_ENV['LOG_PATH'] ?? $defaultPath));
                $days = (int) ($channelConfig['days'] ?? ($_ENV['LOG_DAILY_DAYS'] ?? 14));
                $handler = new RotatingFileHandler($path, $days, $level, $bubble);
                $handler->setFormatter($formatter);
                return $handler;

            case 'stdout':
                $stream = (string) ($channelConfig['stream'] ?? 'php://stdout');
                $handler = new StreamHandler($stream, $level, $bubble);
                $handler->setFormatter($formatter);
                return $handler;

            case 'stderr':
                $stream = (string) ($channelConfig['stream'] ?? 'php://stderr');
                $handler = new StreamHandler($stream, $level, $bubble);
                $handler->setFormatter($formatter);
                return $handler;

            case 'syslog':
                $ident = (string) ($channelConfig['ident'] ?? 'pivotphp');
                $facility = $channelConfig['facility'] ?? LOG_USER;
                $handler = new SyslogHandler($ident, $facility, $level, $bubble);
                $handler->setFormatter($formatter);
                return $handler;

            case 'null':
                return new NullHandler();

            case 'stack':
                /** @var list<string> $stackChannels */
                $stackChannels = (array) ($channelConfig['channels'] ?? ['single']);
                $handlers = [];
                foreach ($stackChannels as $subChannel) {
                    // Prevent infinite recursion if stack references itself
                    if ($subChannel !== $channel) {
                        $handlers[] = self::resolveHandler($subChannel, $config);
                    }
                }
                return new GroupHandler($handlers, $bubble);

            default:
                throw new InvalidArgumentException("Unsupported log channel driver: '{$driver}'");
        }
    }

    /**
     * Resolves a FormatterInterface instance based on string identifier or instance.
     *
     * @param FormatterInterface|string|null $formatter
     */
    public static function resolveFormatter(mixed $formatter): FormatterInterface
    {
        if ($formatter instanceof FormatterInterface) {
            return $formatter;
        }

        if ($formatter === 'json') {
            return new JsonLogFormatter();
        }

        // Default: LineFormatter with ISO standard timestamp and inline linebreaks
        return new LineFormatter(
            format: "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            dateFormat: 'Y-m-d H:i:s',
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true
        );
    }

    /**
     * Parses a log level name or integer into a Monolog\Level enum instance.
     */
    public static function parseLevel(string|int|Level $level, Level $default = Level::Debug): Level
    {
        if ($level instanceof Level) {
            return $level;
        }

        if (is_int($level)) {
            $fromInt = Level::tryFrom($level);
            return $fromInt ?? $default;
        }

        $upper = strtoupper(trim($level));

        return match ($upper) {
            'DEBUG' => Level::Debug,
            'INFO' => Level::Info,
            'NOTICE' => Level::Notice,
            'WARNING' => Level::Warning,
            'ERROR' => Level::Error,
            'CRITICAL' => Level::Critical,
            'ALERT' => Level::Alert,
            'EMERGENCY' => Level::Emergency,
            default => $default,
        };
    }
}
