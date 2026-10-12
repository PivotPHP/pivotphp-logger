<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Tests\Processors;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use PivotPHP\Logger\Processors\MaskSensitiveDataProcessor;

class MaskSensitiveDataProcessorTest extends TestCase
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createRecord(array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: $context,
            extra: $extra
        );
    }

    public function testMasksDefaultSensitiveKeysInContext(): void
    {
        $processor = new MaskSensitiveDataProcessor();

        $record = $this->createRecord([
            'username' => 'john_doe',
            'password' => 'super_secret_123',
            'token' => 'jwt.token.abc',
            'cpf' => '123.456.789-00',
            'status' => 'active',
        ]);

        $processed = $processor($record);

        $this->assertSame('john_doe', $processed->context['username']);
        $this->assertSame('********', $processed->context['password']);
        $this->assertSame('********', $processed->context['token']);
        $this->assertSame('********', $processed->context['cpf']);
        $this->assertSame('active', $processed->context['status']);
    }

    public function testCaseInsensitiveKeyMatching(): void
    {
        $processor = new MaskSensitiveDataProcessor();

        $record = $this->createRecord([
            'PASSWORD' => 'secret1',
            'Token' => 'secret2',
            'API_KEY' => 'key123',
        ]);

        $processed = $processor($record);

        $this->assertSame('********', $processed->context['PASSWORD']);
        $this->assertSame('********', $processed->context['Token']);
        $this->assertSame('********', $processed->context['API_KEY']);
    }

    public function testRecursiveMaskingInNestedArrays(): void
    {
        $processor = new MaskSensitiveDataProcessor();

        $record = $this->createRecord([
            'user' => [
                'name' => 'Alice',
                'credentials' => [
                    'password' => 'secret',
                    'refresh_token' => 'rf_token_999',
                ],
            ],
        ]);

        $processed = $processor($record);

        $this->assertSame('Alice', $processed->context['user']['name']);
        $this->assertSame('********', $processed->context['user']['credentials']['password']);
        $this->assertSame('********', $processed->context['user']['credentials']['refresh_token']);
    }

    public function testMasksExtraRecordFields(): void
    {
        $processor = new MaskSensitiveDataProcessor();

        $record = $this->createRecord(
            context: ['info' => 'ok'],
            extra: ['secret' => 'extra_secret', 'env' => 'prod']
        );

        $processed = $processor($record);

        $this->assertSame('********', $processed->extra['secret']);
        $this->assertSame('prod', $processed->extra['env']);
    }

    public function testCustomKeysAndMask(): void
    {
        $processor = new MaskSensitiveDataProcessor(
            sensitiveKeys: ['ssn', 'pin'],
            mask: '[REDACTED]'
        );

        $record = $this->createRecord([
            'ssn' => '999-00-1111',
            'pin' => '1234',
            'password' => 'unaffected_since_not_in_custom_list',
        ]);

        $processed = $processor($record);

        $this->assertSame('[REDACTED]', $processed->context['ssn']);
        $this->assertSame('[REDACTED]', $processed->context['pin']);
        $this->assertSame('unaffected_since_not_in_custom_list', $processed->context['password']);
    }
}
