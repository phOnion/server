<?php

namespace Onion\Framework\Server\Drivers;

use InvalidArgumentException;
use Onion\Framework\Loop\Descriptor;
use Onion\Framework\Loop\Interfaces\ResourceInterface;
use Onion\Framework\Loop\Interfaces\SchedulerInterface;
use Onion\Framework\Loop\Interfaces\TaskInterface;
use Onion\Framework\Loop\Task;
use Onion\Framework\Server\Drivers\Types\Scheme;
use Onion\Framework\Server\Events\CloseEvent;
use Onion\Framework\Server\Events\ConnectEvent;
use Onion\Framework\Server\Events\MessageEvent;
use Onion\Framework\Server\Events\PacketEvent;
use Onion\Framework\Server\Interfaces\ContextInterface;
use Onion\Framework\Server\Interfaces\DriverInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

use function Onion\Framework\Loop\coroutine;
use function Onion\Framework\Loop\signal;

class NetworkDriver implements DriverInterface
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function listen(
        string $address,
        ?int $port,
        ContextInterface ...$contexts,
    ): void {

        $ctx = [];
        foreach ($contexts as $context) {
            $ctx = array_merge($ctx, $context->getContextArray());
        }

        $port = $port ? ":{$port}" : '';
        [$scheme, $location] = $this->getAddressComponents($address);
        $flags = STREAM_SERVER_BIND | match ($scheme) {
            Scheme::TCP, Scheme::UNIX => STREAM_SERVER_LISTEN,
            default => 0,
        };

        $socket = stream_socket_server(
            "{$scheme->value}://{$location}{$port}",
            $error,
            $message,
            $flags,
            stream_context_create($ctx),
        );

        if (!$socket) {
            throw new RuntimeException($message, $error);
        }

        $socket = new Descriptor($socket);
        $socket->unblock();

        while (!$socket->eof()) {
            $connection = signal(function (
                callable $resume,
                TaskInterface $task,
                SchedulerInterface $scheduler
            ) use (
                $scheme,
                $socket
            ) {
                $scheduler->schedule(Task::create(function (
                    TaskInterface $task,
                    SchedulerInterface $scheduler,
                    ResourceInterface $resource,
                    Scheme $scheme,
                ) {
                    try {
                        $resource->wait();
                        if ($scheme === Scheme::TCP || $scheme === Scheme::UNIX) {
                            $resource = new Descriptor(stream_socket_accept($resource->getResource(), 0));
                            $resource->unblock();
                        }

                        $task->resume($resource);
                    } catch (\Throwable $ex) {
                        $task->throw($ex);
                    } finally {
                        $scheduler->schedule($task);
                    }
                }, [$task, $scheduler, $socket, $scheme]));
            });

            coroutine(function (Scheme $scheme, ResourceInterface $connection) {
                if ($scheme === Scheme::TCP || $scheme === Scheme::UNIX) {
                    $this->dispatcher->dispatch(new ConnectEvent($connection));
                    while (!$connection->eof()) {
                        $connection->wait();
                        $this->dispatcher->dispatch(new MessageEvent($connection));
                    }
                    $this->dispatcher->dispatch(new CloseEvent($connection));
                } else {
                    $this->dispatcher->dispatch(new PacketEvent($connection));
                }
            }, [$scheme, $connection]);
        }
    }

    private function getAddressComponents(string $address): array
    {
        if (preg_match('/^(?P<scheme>[a-z0-9]+)\:\/\/(?P<rest>.*)$/i', $address, $matches) !== 0) {
            return [
                Scheme::from(strtolower($matches['scheme'])),
                $matches['rest'],
            ];
        }

        throw new InvalidArgumentException(
            "Unable to determine scheme from provided scheme '{$address}', format needs to be `{scheme}://...`"
        );
    }
}
