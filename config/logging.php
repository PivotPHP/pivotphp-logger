<?php

declare(strict_types=1);

/**
 * PivotPHP Logger Configuration
 */
return [
    'default' => $_ENV['LOG_CHANNEL'] ?? 'daily',
    'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'stdout'],
        ],
        'single' => [
            'driver' => 'single',
            'path' => $_ENV['LOG_PATH'] ?? 'storage/logs/pivotphp.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'formatter' => 'line',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => $_ENV['LOG_PATH'] ?? 'storage/logs/pivotphp.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'days' => (int) ($_ENV['LOG_DAILY_DAYS'] ?? 14),
            'formatter' => 'line',
        ],
        'stdout' => [
            'driver' => 'stdout',
            'stream' => 'php://stdout',
            'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            'formatter' => 'json',
        ],
        'stderr' => [
            'driver' => 'stderr',
            'stream' => 'php://stderr',
            'level' => 'error',
            'formatter' => 'line',
        ],
        'syslog' => [
            'driver' => 'syslog',
            'ident' => 'pivotphp',
            'level' => 'notice',
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
    'mask_sensitive' => true,
];
