<?php

declare(strict_types=1);

namespace PivotPHP\Logger\Tests;

use PHPUnit\Framework\TestCase;
use PivotPHP\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class LoggerFactoryTest extends TestCase
{
    public function testCreateReturnsLoggerInterface(): void
    {
        $logger = LoggerFactory::create();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
}
