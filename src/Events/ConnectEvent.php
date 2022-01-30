<?php

namespace Onion\Framework\Server\Events;

use Psr\EventDispatcher\StoppableEventInterface;

class ConnectEvent extends ConnectionEvent implements StoppableEventInterface
{
    use StoppableTrait;
}
