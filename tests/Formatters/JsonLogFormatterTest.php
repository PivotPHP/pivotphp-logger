<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Tests\Formatters;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use PivotPHP\Logger\Formatters\JsonLogFormatter;

class JsonLogFormatterTest extends TestCase
{
    public function testFormatsRecordToJsonStructure(): void
    {
        $formatter = new JsonLogFormatter();

        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-10-12T12:00:00+00:00'),
            channel: 'api',
            level: Level::Info,
            message: 'User logged in',
            context: ['user_id' => 42],
            extra: ['ip' => '127.0.0.1']
        );

        $output = $formatter->format($record);

        $this->assertStringEndsWith("\n", $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('api', $decoded['channel']);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('User logged in', $decoded['message']);
        $this->assertSame(['user_id' => 42], $decoded['context']);
        $this->assertSame(['ip' => '127.0.0.1'], $decoded['extra']);
        $this->assertArrayHasKey('timestamp', $decoded);
    }

    public function testIgnoreEmptyContextAndExtraWhenConfigured(): void
    {
        $formatter = new JsonLogFormatter(
            ignoreEmptyContextAndExtra: true
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'cli',
            level: Level::Warning,
            message: 'Memory threshold warning',
            context: [],
            extra: []
        );

        $output = $formatter->format($record);
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('context', $decoded);
        $this->assertArrayNotHasKey('extra', $decoded);
    }

    public function testHandlesNonUtf8StringsWithoutThrowing(): void
    {
        $formatter = new JsonLogFormatter();

        $invalidUtf8 = chr(193) . chr(175);
        $latin1 = (string) iconv('UTF-8', 'ISO-8859-1', 'Ação');

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'encoding',
            level: Level::Info,
            message: 'Mensagem com ISO: ' . $latin1,
            context: [
                'binary' => $invalidUtf8,
                'latin1' => $latin1,
            ],
            extra: []
        );

        $output = $formatter->format($record);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('encoding', $decoded['channel']);
        $this->assertArrayHasKey('binary', $decoded['context']);
        $this->assertArrayHasKey('latin1', $decoded['context']);
    }
}
