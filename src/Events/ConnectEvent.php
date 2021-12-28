<?php

namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class ConnectEvent extends ConnectionEvent implements StoppableEventInterface
{
    use StoppableTrait;
}
