<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Processor to mask sensitive keys (passwords, tokens, secrets, etc.) in log context and extra data.
 */
class MaskSensitiveDataProcessor implements ProcessorInterface
{
    /**
     * @var list<string>
     */
    private array $sensitiveKeys;

    private string $mask;

    /**
     * @param list<string> $sensitiveKeys List of key names to mask (case-insensitive)
     * @param string $mask Replacement string for sensitive values
     */
    public function __construct(
        array $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'api_key',
            'apikey',
            'authorization',
            'auth',
            'cpf',
            'card_number',
            'cvv',
            'credit_card',
        ],
        string $mask = '********'
    ) {
        $this->sensitiveKeys = array_values(array_map('strtolower', $sensitiveKeys));
        $this->mask = $mask;
    }

    /**
     * Masks sensitive entries within context and extra records.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $context */
        $context = $this->maskArray($record->context);

        /** @var array<string, mixed> $extra */
        $extra = $this->maskArray($record->extra);

        return $record->with(
            context: $context,
            extra: $extra
        );
    }

    /**
     * Recursively masks sensitive keys in arrays.
     *
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function maskArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $isSensitive = is_string($key) && in_array(strtolower($key), $this->sensitiveKeys, true);

            if ($isSensitive) {
                $result[$key] = $this->mask;
            } elseif (is_array($value)) {
                $result[$key] = $this->maskArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
