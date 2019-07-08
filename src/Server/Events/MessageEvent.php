<?php
namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;

class MessageEvent
{
    private $connection;
    private $message;

    public function __construct(ResourceInterface $resource)
    {
        $this->connection = $resource;
    }

    public function getConnection(): ResourceInterface
    {
        return $this->connection;
    }
}
