<?php

namespace Onion\Framework\Server\Events;

use Onion\Framework\Loop\Interfaces\ResourceInterface;

abstract class ConnectionEvent
{
    private ResourceInterface $resource;

    public function __construct(ResourceInterface $resource)
    {
        $this->resource = $resource;
    }

    public function getConnection(): ResourceInterface
    {
        return $this->resource;
    }
}
