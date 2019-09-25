<?php
namespace Onion\Framework\Server;

use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Server\Events\StartEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Onion\Framework\Server\Interfaces\ServerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class Server implements ServerInterface
{
    private $dispatcher;
    private $drivers;

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

    public function start(): Coroutine {
        return new Coroutine(function () {
            foreach ($this->drivers as $data) {
                yield Coroutine::create(function () use ($data) {
                    [$driver, $address, $port, $contexts] = $data;

                    yield from $driver->listen($address, $port, ...($contexts ?? []));
                });
            }

            yield $this->dispatcher->dispatch(new StartEvent());
        });
    }
}
