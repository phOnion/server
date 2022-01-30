<?php

namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;

abstract class ConnectionEvent
{
    public function __construct(
        public readonly ResourceInterface $connection
    ) {
    }
}
