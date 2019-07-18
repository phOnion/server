<?php
namespace Onion\Framework\Server\Drivers;

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

        yield $socket->wait();
        while ($socket->isAlive()) {
            try {
                $connection = yield $socket->accept();
            } catch (\InvalidArgumentException $ex) {
                // Accept failed, we ok
                continue;
            }

            yield $this->dispatcher->dispatch(new ConnectEvent($connection));

            while ($connection->isAlive()) {
                yield $connection->wait();

                yield $this->dispatcher->dispatch(new MessageEvent($connection));
                if (!$connection->isAlive()) {
                    break;
                }
                yield;
            }

            if (!$connection->isAlive()) {
                yield $this->dispatcher->dispatch(new CloseEvent);
                continue;
            }

            yield;
        }

        yield $this->dispatcher->dispatch(new StopEvent($socket));
    }
}
