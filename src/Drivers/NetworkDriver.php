<?php

namespace Onion\Framework\Server\Drivers;

use Closure;
use Onion\Framework\Loop\Interfaces\SchedulerInterface;
use Onion\Framework\Loop\Interfaces\TaskInterface;
use Onion\Framework\Loop\Types\NetworkAddress;
use Onion\Framework\Loop\Types\NetworkProtocol;
use Onion\Framework\Server\Contexts\AggregateContext;
use Onion\Framework\Server\Drivers\Types\Scheme;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;

use function Onion\Framework\Loop\signal;

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

    public function listen(Closure $callback): void
    {
        signal(fn ($resume, TaskInterface $task, SchedulerInterface $scheduler) => $resume($scheduler->open(
            $this->address,
            $this->port,
            $callback,
            match ($this->kind) {
                Scheme::TCP, Scheme::UNIX => NetworkProtocol::TCP,
                Scheme::UDP, Scheme::UDG => NetworkProtocol::UDP,
            },
            new AggregateContext($this->contexts ?? []),
            match ($this->kind) {
                Scheme::UNIX, Scheme::UDG => NetworkAddress::LOCAL,
                Scheme::TCP, Scheme::UDP => NetworkAddress::NETWORK
            }
        )));
    }
}
