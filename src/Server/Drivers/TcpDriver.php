<?php
namespace Onion\Framework\Server\Drivers;

use LogicException;
use Onion\Framework\Loop\Coroutine;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Server\Drivers\DriverTrait;
use Onion\Framework\Server\Events\CloseEvent;
use Onion\Framework\Server\Events\ConnectEvent;
use Onion\Framework\Server\Events\MessageEvent;
use Onion\Framework\Server\Events\StopEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class TcpDriver implements DriverInterface
{
    protected const SOCKET_FLAGS = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

    private $dispatcher;

    use DriverTrait;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getScheme(string $address): string
    {
        return file_exists($address) ? 'unix' : 'tcp';
    }

    public function listen(string $address, ?int $port, ContextInterface ...$contexts): \Generator
    {
        $socket = $this->createSocket($address, $port, $contexts);

        while ($socket->isAlive()) {
            try {
                $connection = yield $socket->accept();

                yield Coroutine::create(function (ResourceInterface $connection, EventDispatcherInterface $dispatcher) {

                    try {
                        /** @var ConnectEvent $event */
                        $event = yield $dispatcher->dispatch(new ConnectEvent($connection));

                        while (!$event->isPropagationStopped() && $connection->isAlive()) {
                            yield $connection->wait();

                            yield $dispatcher->dispatch(new MessageEvent($connection));
                            if (!$connection->isAlive()) {
                                break;
                            }
                            yield;
                        }

                        if (!$connection->isAlive()) {
                            yield $dispatcher->dispatch(new CloseEvent);
                        }
                    } catch (\LogicException $ex) {
                        // Probably stream died mid event dispatching
                    }

                }, [$connection, $this->dispatcher]);
            } catch (\InvalidArgumentException | LogicException $ex) {
                // Accept failed, we ok
            }

            yield;
        }

        yield $this->dispatcher->dispatch(new StopEvent($socket));
    }
}
