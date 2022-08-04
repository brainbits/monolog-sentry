<?php

declare(strict_types=1);

namespace Brainbits\MonologSentryTests\EventListener;

use Monolog\ResettableInterface;
use Psr\Log\LoggerInterface;

interface ResettableLogger extends LoggerInterface, ResettableInterface
{
}
