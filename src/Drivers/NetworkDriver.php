<?php

namespace Onion\Framework\Server\Drivers;

use Onion\Framework\Loop\Types\NetworkAddress;
use Onion\Framework\Loop\Types\NetworkProtocol;
use Onion\Framework\Server\Contexts\AggregateContext;
use Onion\Framework\Server\Drivers\Types\Scheme;
use Onion\Framework\Server\Events\MessageEvent;
use Onion\Framework\Server\Events\PacketEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function Onion\Framework\Loop\scheduler;

class NetworkDriver implements DriverInterface
{
    private readonly array $contexts;

    public function __construct(
        public readonly Scheme $kind,
        public readonly string $address,
        public readonly int $port = 0,
        ContextInterface ...$contexts,
    ) {
        $this->contexts = array_merge(...array_map(fn(ContextInterface $c) => $c->getContextArray(), $contexts));
    }

    public function listen(EventDispatcherInterface $dispatcher): void
    {
        $scheduler = scheduler();
        $scheduler->open(
            $this->address,
            $this->port,
            fn ($connection) => $dispatcher->dispatch(
                match($this->kind) {
                    Scheme::TCP, Scheme::UNIX => new MessageEvent($connection),
                    Scheme::UDP, Scheme::UDG => new PacketEvent($connection),
                }
            ),
            match ($this->kind) {
                Scheme::TCP, Scheme::UNIX => NetworkProtocol::TCP,
                Scheme::UDP, Scheme::UDG => NetworkProtocol::UDP,
            },
            new AggregateContext($this->contexts ?? []),
            match ($this->kind) {
                Scheme::UNIX, Scheme::UDG => NetworkAddress::LOCAL,
                Scheme::TCP, Scheme::UDP => NetworkAddress::NETWORK
            }
        );
    }
}
