<?php
namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;

class ConnectEvent
{
    private $connection;

    public function __construct(ResourceInterface $resource)
    {
        $this->connection = $resource;
    }

    public function getConnection(): ResourceInterface
    {
        return $this->connection;
    }
}
