<?php

namespace Onion\Framework\Server;

use Onion\Framework\Server\Events\StartEvent;
use Onion\Framework\Server\Events\StopEvent;
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

    public function attach(DriverInterface $driver): void
    {
        $this->drivers[] = $driver;
    }

    public function start(): void
    {
        coroutine(function (): void {
            foreach ($this->drivers as $driver) {
                coroutine($driver->listen(...), [$this->dispatcher]);
            }

            $this->dispatcher->dispatch(new StartEvent());
        });

        register_shutdown_function(fn () => coroutine(fn () => $this->dispatcher->dispatch(new StopEvent())));
    }
}
