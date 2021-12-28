<?php

namespace Onion\Framework\Server;

use Generator;
use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Server\Events\StartEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Onion\Framework\Server\Interfaces\ServerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function Onion\Framework\Loop\coroutine;
use function Onion\Framework\Loop\tick;

class Server implements ServerInterface
{
    private EventDispatcherInterface $dispatcher;
    private array $drivers;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->drivers = [];
    }

    public function attach(
        DriverInterface $driver,
        string $address,
        ?int $port = null,
        ContextInterface ...$contexts
    ): void {
        $this->drivers[] = [$driver, $address, $port, $contexts];
    }

    public function start(): void
    {
        coroutine(function (): void {
            foreach ($this->drivers as $data) {
                coroutine(function () use ($data) {
                    [$driver, $address, $port, $contexts] = $data;

                    $driver->listen($address, $port, ...($contexts ?? []));
                });
                tick();
            }

            $this->dispatcher->dispatch(new StartEvent());
        });
    }
}
