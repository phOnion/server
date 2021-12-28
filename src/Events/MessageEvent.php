<?php

namespace Onion\Framework\Server\Events;

use Psr\EventDispatcher\StoppableEventInterface;

class MessageEvent extends ConnectionEvent implements StoppableEventInterface
{
    use StoppableTrait;
}
