<?php
namespace Onion\Framework\Server;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\loop;
use function Onion\Framework\Promise\async;
use Onion\Framework\EventLoop\Stream\Interfaces\StreamInterface;
use Onion\Framework\EventLoop\Stream\Stream as TcpStream;
use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Promise\RejectedPromise;
use Onion\Framework\Server\Interfaces\ServerInterface;
use Onion\Framework\Server\Udp\Stream as UdpStream;
use function Onion\Framework\EventLoop\detach;

class Server implements ServerInterface
{
    private $listeners = [];
    private $handlers = [];

    private $configuration = [];

    public function addListener(string $interface, ?int $port = 0, int $type = 0, array $options = [])
    {
        $this->listeners[] = [
            'interface' => $interface,
            'port' => $port,
            'type' => $type,
            'options' => $options,
        ];
    }

    public function on(string $event, callable $callback)
    {
        $this->handlers[strtolower($event)] = $callback;
    }

    public function set(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getMaxPackageSize(): int
    {
        return $this->configs['package_max_length'] ?? 2097152; // Default 2MBs
    }

    protected function init(): PromiseInterface
    {
        $promises = [];
        foreach ($this->listeners as $listener) {
            $interface = $port = $type = $options = null;

            extract(array_filter($listener, function ($value) {
                return $value !== null || (is_array($value) && !empty($value));
            }), EXTR_IF_EXISTS | EXTR_OVERWRITE);

            $promises[] = async(function () use ($interface, $port, $type, $options) {
                $address = null;
                $flags = 0;

                if (($type & self::TYPE_SOCK) === self::TYPE_SOCK) {
                    $address = "unix://{$interface}";
                    $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                } else if (($type & self::TYPE_TCP) === self::TYPE_TCP) {
                    $address = "tcp://{$interface}:{$port}";
                    $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                } else if (($type & self::TYPE_UDP) === self::TYPE_UDP) {
                    $address = "udp://{$interface}:{$port}";
                    $flags = STREAM_SERVER_BIND;
                } else {
                    throw new \RuntimeException(
                        "Unable to determine server type for '{$interface}:{$port}'"
                    );
                }

                $socket = @stream_socket_server($address, $errCode, $errMessage, $flags, $this->createContext(array_merge(
                    $this->configuration,
                    $options ?? []
                ), ($type & self::TYPE_SECURE) === self::TYPE_SECURE));

                if (!$socket) {
                    throw new \RuntimeException(
                        "Unable to bind on '{$address}' - {$errMessage} ({$errCode})",
                        $errCode
                    );
                }
                stream_set_blocking($socket, false);

                return $socket;
            })->otherwise(function (\Throwable $ex) {
                echo "Error ({$ex->getMessage()})\n";

                return $ex;
            });
        }

        return Promise::all($promises)
            ->then(function ($streams) {
                foreach ($streams as $stream) {
                    if (stream_get_meta_data($stream)['stream_type'] === 'udp_socket') {
                        $this->handleUdp($stream);
                        continue;
                    }

                    $this->handleTcp($stream);
                }
            })->then(function ($sockets) {
                foreach ($sockets as $socket) {
                    echo "Server " . stream_socket_get_name($socket, false) . " - Ready\n";
                }

                $this->trigger('start');
            });
    }

    protected function trigger(string $event, ... $args)
    {
        return async(function () use ($event, $args) {
            if (!isset($this->handlers[$event])) {
                return new RejectedPromise(
                    new \BadMethodCallException("No handler defined for '{$event}'")
                );
            }

            return call_user_func_array($this->handlers[$event], $args);
        });
    }

    private function createContext(array $configs = [], bool $secure = false)
    {
        $context = stream_context_create();
        if (isset($configs['backlog'])) {
            stream_context_set_option($context, 'tcp', 'backlog', $configs['backlog']);
        }

        if (isset($this->configs['tcp_nodelay'])) {
            stream_context_set_option($context, 'tcp', 'tcp_nodelay', $configs['tcp_nodelay']);
        }

        if ($secure) {
            $options = [
                'local_cert' => $configs['ssl_cert_file'] ?? null,
                'local_pk' => $configs['ssl_key_file'] ?? null,
                'verify_peer' => $configs['ssl_verify_peer'] ?? null,
                'allow_self_signed' => $configs['ssl_allow_self_signed'] ?? null,
                'verify_depth' => $configs['ssl_verify_depth'] ?? null,
                'cafile' => $this->configs['ssl_client_cert_file'] ?? null,
                'passphrase' => $configs['ssl_cert_passphrase'] ?? null,
            ];

            $options = array_filter($options, function($value) {
                return $value !== null;
            });

            foreach ($options as $key => $value) {
                stream_context_set_option($context, 'ssl', $key, $value);
            }
        }

        return $context;
    }


    public function handleTcp($socket)
    {
        attach($socket, function (StreamInterface $stream) {
            $socket = $stream->detach();
            stream_set_blocking($socket, false);

            if (stream_context_get_options($socket)['ssl'] ?? false) {
                stream_set_blocking($socket, true);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
                    @fclose($socket);
                    return;
                }
            }

            $channel = @stream_socket_accept($socket);
            @stream_set_read_buffer($channel, $this->getMaxPackageSize() + 8192);
            stream_set_blocking($channel, false);

            $this->trigger('connect', new TcpStream($channel));

            attach($channel, function (StreamInterface $stream) {
                if ($stream->isClosed()) {
                    $stream->close();
                    detach($stream->detach());
                    echo "Closing\n";
                    $this->trigger('close');
                    return;
                }

                $this->trigger('receive', $stream, $stream->read($this->getMaxPackageSize()));
            });
        });
    }

    public function handleUdp($socket)
    {
        stream_set_blocking($socket, false);
        attach($socket, function (TcpStream $stream) {
            $stream = new UdpStream($stream->detach());

            if (!($data = $stream->peek(8192, false, $address))) {
                return;
            }

            $this->trigger('packet', $stream, $data, $address);
        });
    }

    public function start()
    {
        $this->init();

        loop()->start();
    }

}
