<?php

namespace Onion\Framework\Server;

use Onion\Framework\Server\Interfaces\DriverInterface;
use Onion\Framework\Server\Interfaces\ServerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function Onion\Framework\Loop\coroutine;

class Server implements ServerInterface
{
    private EventDispatcherInterface $dispatcher;
    private array $drivers;

    public function __construct()
    {
        $this->drivers = [];
    }

    public function attach(DriverInterface $driver, \Closure $cb): void
    {
        $this->drivers[] = [$driver, $cb];
    }

    public function start(): void
    {
        coroutine(function (): void {
            foreach ($this->drivers as [$driver, $cb]) {
                coroutine($driver->listen(...), [$cb]);
            }
        });
    }
}
