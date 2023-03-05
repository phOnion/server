<?php

namespace Onion\Framework\Server\Drivers;

use Closure;
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
    private readonly array $contexts;
    public function __construct(
        private readonly Scheme $kind,
        private readonly string $address,
        private readonly int $port = 0,
        ContextInterface ...$contexts,
    ) {
        $this->contexts = array_merge(...array_map(fn(ContextInterface $c) => $c->getContextArray(), $contexts));
    }

    public function listen(EventDispatcherInterface $dispatcher): void
    {
        $flags = STREAM_SERVER_BIND | match ($this->kind) {
            Scheme::TCP, Scheme::UNIX => STREAM_SERVER_LISTEN,
            default => 0,
        };

        $address = match ($this->kind) {
            Scheme::UNIX, Scheme::UDG => $this->address,
            default => "{$this->address}:{$this->port}",
        };

        $socket = stream_socket_server(
            "{$this->kind->value}://{$address}",
            $error,
            $message,
            $flags,
            stream_context_create($this->contexts),
        );

        if (!$socket) {
            throw new RuntimeException($message, $error);
        }


        [$address, $port] = explode(':', stream_socket_get_name($socket, false), 2);
        $socket = new Descriptor($socket);
        $socket->unblock();

        $this->accept($socket, $dispatcher);
    }

    public function accept(ResourceInterface $socket, EventDispatcherInterface $dispatcher): void
    {
        signal(function (
            callable $resume,
            TaskInterface $task,
            SchedulerInterface $scheduler,
        ) use (
            $socket,
            $dispatcher
        ) {
            $scheduler->onRead($socket, Task::create(
                function ($dispatcher) use ($socket, $scheduler) {
                    $connection = $socket;
                    if ($this->kind === Scheme::TCP || $this->kind === Scheme::UNIX) {
                        $connection = new Descriptor(
                            stream_socket_accept($socket->getResource(), 0)
                        );
                        $connection->unblock();
                    }
                    coroutine($this->accept(...), [$socket, $dispatcher]);
                    $scheduler->onRead($connection, Task::create(
                        function ($dispatcher) use ($connection) {
                            coroutine($this->handle(...), [$connection, $dispatcher]);
                        },
                        [$dispatcher]
                    ));
                },
                [$dispatcher]
            ));
            $resume();
        });
    }

    private function handle(ResourceInterface $connection, EventDispatcherInterface $dispatcher): void
    {
        if ($this->kind === Scheme::TCP || $this->kind === Scheme::UNIX) {
            $dispatcher->dispatch(new ConnectEvent($connection));
            signal(function (
                Closure $resume,
                TaskInterface $task,
                SchedulerInterface $scheduler,
            ) use (
                $dispatcher,
                $connection,
            ) {
                $scheduler->onRead(
                    $connection,
                    Task::create(
                        function (ResourceInterface $connection, EventDispatcherInterface $dispatcher) {
                            if (!$connection->eof()) {
                                $dispatcher->dispatch(new MessageEvent($connection));
                                return $this->handle($connection, $dispatcher);
                            }


                            $connection->close();
                            $dispatcher->dispatch(new CloseEvent($connection));
                        },
                        [$connection, $dispatcher]
                    )
                );

                $resume();
            });
        } else {
            $dispatcher->dispatch(new PacketEvent($connection));
        }
    }
}
