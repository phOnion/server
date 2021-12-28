<?php

namespace Onion\Framework\Server\Drivers;

use InvalidArgumentException;
use LogicException;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Server\Drivers\DriverTrait;
use Onion\Framework\Server\Events\CloseEvent;
use Onion\Framework\Server\Events\ConnectEvent;
use Onion\Framework\Server\Events\ConnectionEvent;
use Onion\Framework\Server\Events\MessageEvent;
use Onion\Framework\Server\Events\StopEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

use function Onion\Framework\Loop\coroutine;

class TcpDriver implements DriverInterface
{
    use DriverTrait;

    private EventDispatcherInterface $dispatcher;


    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getScheme(string $address): string
    {
        return file_exists($address) ? 'unix' : 'tcp';
    }

    public function listen(string $address, ?int $port, ContextInterface ...$contexts): void
    {
        $socket = $this->createSocket($address, $port, $contexts, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);

        while ($socket->isAlive()) {
            try {
                $connection = $socket->accept();

                coroutine(function (ResourceInterface $connection, EventDispatcherInterface $dispatcher) {

                    try {
                        /** @var ConnectEvent $event */
                        $event = $dispatcher->dispatch(new ConnectEvent($connection));
                        $connection = $event->getConnection();
                        while ($connection->isAlive()) {
                            $connection->wait();

                            $dispatcher->dispatch(new MessageEvent($connection));
                            if (!$connection->isAlive()) {
                                break;
                            }
                        }

                        if (!$connection->isAlive()) {
                            $dispatcher->dispatch(new CloseEvent($connection));
                        }
                    } catch (LogicException $ex) {
                        // Probably stream died mid event dispatching
                    }
                }, [$connection, $this->dispatcher]);
            } catch (InvalidArgumentException | LogicException $ex) {
                // Accept failed, we ok
            }
        }

        $this->dispatcher->dispatch(new CloseEvent($socket));
        $this->dispatcher->dispatch(new StopEvent());
    }
}
