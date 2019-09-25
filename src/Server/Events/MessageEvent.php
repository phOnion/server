<?php
namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class MessageEvent implements StoppableEventInterface
{
    private $connection;

    use StoppableTrait;

    public function __construct(ResourceInterface $resource)
    {
        $this->connection = $resource;
    }

    public function getConnection(): ResourceInterface
    {
        return $this->connection;
    }
}
