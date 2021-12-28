<?php

namespace Onion\Framework\Server\Drivers;

use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Server\Events\PacketEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class UdpDriver implements DriverInterface
{
    use DriverTrait;

    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getScheme(string $address): string
    {
        return file_exists($address) ? 'unix' : 'udp';
    }

    public function listen(string $address, ?int $port, ContextInterface ...$contexts): void
    {
        $socket = $this->createSocket($address, $port, $contexts, STREAM_SERVER_BIND);


        while ($socket->isAlive()) {
            $socket->wait();

            $this->dispatcher->dispatch(new PacketEvent($socket));
        }
    }
}
