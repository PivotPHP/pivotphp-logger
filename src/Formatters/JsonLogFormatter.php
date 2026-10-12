<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Formatters;

use DateTimeInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Structured JSON log formatter optimized for cloud observability and log aggregators.
 */
class JsonLogFormatter extends JsonFormatter
{
    /**
     * @param self::BATCH_MODE_* $batchMode Batch mode (default: BATCH_MODE_JSON)
     * @param bool $appendNewline Whether to append a newline character to each output
     * @param bool $ignoreEmptyContextAndExtra Whether to strip context and extra when empty
     * @param bool $includeStacktraces Whether to include exception stack traces
     */
    public function __construct(
        int $batchMode = self::BATCH_MODE_JSON,
        bool $appendNewline = true,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = true
    ) {
        parent::__construct(
            $batchMode,
            $appendNewline,
            $ignoreEmptyContextAndExtra,
            $includeStacktraces
        );
        $this->dateFormat = DateTimeInterface::RFC3339_EXTENDED;
    }

    /**
     * Formats a log record into a structured JSON line.
     */
    public function format(LogRecord $record): string
    {
        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizeRecord($record);

        $payload = [
            'timestamp' => $record->datetime->format($this->dateFormat),
            'channel' => $record->channel,
            'level' => $record->level->getName(),
            'message' => $record->message,
            'context' => $normalized['context'] ?? [],
            'extra' => $normalized['extra'] ?? [],
        ];

        if ($this->ignoreEmptyContextAndExtra) {
            if (empty($payload['context'])) {
                unset($payload['context']);
            }
            if (empty($payload['extra'])) {
                unset($payload['extra']);
            }
        }

        return $this->toJson($payload, true) . ($this->appendNewline ? "\n" : '');
    }
}
