<?php

namespace Onion\Framework\Server\Drivers;

use Onion\Framework\Server\Events\RequestEvent;
use Onion\Framework\Loop\{Interfaces\ResourceInterface, Types\Operation};
use Onion\Framework\Server\{
    Events\ConnectEvent,
    Drivers\DriverTrait,
    Interfaces\ContextInterface,
    Interfaces\DriverInterface,
};
use Psr\EventDispatcher\EventDispatcherInterface;

use function Onion\Framework\Server\build_request;
use function Onion\Framework\Loop\coroutine;

class HttpDriver implements DriverInterface
{
    use DriverTrait;

    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function getScheme(string $address): string
    {
        return 'tcp';
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
                        while ($connection->isAlive()) {
                            $connection->wait();

                            $data = '';
                            while (($chunk = $connection->read(8192)) !== '') {
                                $data .= $chunk;
                            }

                            $connection->wait(Operation::WRITE);

                            $dispatcher->dispatch(new RequestEvent(build_request($data), $connection));
                        }
                    } catch (\LogicException $ex) {
                        // Probably stream died mid event dispatching
                    }
                }, [$connection, $this->dispatcher]);
            } catch (\Throwable $ex) {
                // Accept failed, we ok
            }
        }
    }
}
