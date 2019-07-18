<?php
namespace Onion\Framework\Server\Drivers;

use Onion\Framework\Server\Events\PacketEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class UdpDriver implements DriverInterface
{
    protected const SOCKET_FLAGS = STREAM_SERVER_BIND;

    private $dispatcher;

    use DriverTrait;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getScheme(string $address): string
    {
        return file_exists($address) ? 'unix' : 'udp';
    }

    public function listen(string $address, ?int $port, ContextInterface ...$contexts): \Generator
    {
        $socket = $this->createSocket($address, $port, $contexts);

        while ($socket->isAlive()) {
            yield $socket->wait();
            yield $this->dispatcher->dispatch(new PacketEvent($socket));
        }
    }
}
