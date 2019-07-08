<?php
namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;

class PacketEvent
{
    private $connection;

    public function __construct(ResourceInterface $socket)
    {
        $this->connection = $socket;
    }

    public function getConnection(): ResourceInterface
    {
        return $this->connection;
    }
}
