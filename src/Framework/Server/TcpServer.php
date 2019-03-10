<?php
namespace Onion\Framework\Server;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\defer;
use function Onion\Framework\EventLoop\detach;
use function Onion\Framework\EventLoop\loop;
use function Onion\Framework\Promise\async;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Promise\RejectedPromise;
use Onion\Framework\Server\Interfaces\ServerInterface;

class TcpServer implements ServerInterface
{
    private const MAX_PAYLOAD_SIZE = 1024 * 1024 * 2;

    private $listeners = [];
    /** @var callable[] $handlers */
    private $handlers = [];

    private $configs = [];

    public function __construct(string $interface, ?int $port = null, int $type = self::TYPE_SOCK, array $options = [])
    {
        $this->listeners[] = [
            'interface' => $interface,
            'port' => $port,
            'type' => $type,
            'options' => $options,
        ];
    }

    public function addListener(string $interface, int $port, int $type, array $options = [])
    {
        $this->listeners[] = [
            'interface' => $interface,
            'port' => $port,
            'type' => $type,
            'config' => $options,
        ];
    }

    public function on(string $event, \Closure $callback)
    {
        $this->handlers[strtolower($event)] = $callback;
    }

    public function set(array $configuration)
    {
        $this->configs = $configuration;
    }

    public function getMaxPackageSize(): int
    {
        return $this->configs['package_max_length'] ?? self::MAX_PAYLOAD_SIZE;
    }

    public function start()
    {
        $this->trigger('start');

        foreach ($this->listeners as $listener) {
            $interface = $port = $type = $config = null;

            extract(array_filter($listener, function ($value) {
                return $value !== null || (is_array($value) && !empty($value));
            }), EXTR_IF_EXISTS | EXTR_OVERWRITE);

            async(function () use ($interface, $port, $type, $config) {
                $address = null;
                $options = 0;

                if (($type & self::TYPE_SOCK) === self::TYPE_SOCK) {
                    $address = "unix://{$interface}";
                    $options = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                } else if (($type & self::TYPE_TCP) === self::TYPE_TCP) {
                    $address = "tcp://{$interface}:{$port}";
                    $options = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
                } else if (($type & self::TYPE_UDP) === self::TYPE_UDP) {
                    $address = "udp://{$interface}:{$port}";
                    $options = STREAM_SERVER_BIND;
                } else {
                    throw new \RuntimeException(
                        "Unable to determine server type for '{$interface}:{$port}'"
                    );
                }

                $socket = @stream_socket_server($address, $errCode, $errMessage, $options, $this->createContext(array_merge(
                    $this->configs,
                    $config ?? []
                ), ($type & self::TYPE_SECURE) === self::TYPE_SECURE));
                stream_set_blocking($socket, 0);

                if (!$socket) {
                    throw new \RuntimeException(
                        "Unable to bind on '{$address}' - {$errMessage} ({$errCode})",
                        $errCode
                    );
                }
                echo "Listening on {$address} ";
                return $socket;
            })->then(function ($socket) {
                stream_set_blocking($socket, 0);

                $secure = (bool) (stream_context_get_options($socket)['ssl'] ?? false);

                attach($socket, function (Stream $stream) use ($secure) {

                    $sock = $stream->detach();
                    $channel = @stream_socket_accept($sock);
                    stream_set_read_buffer($channel, $this->getMaxPackageSize() + 4096);
                    stream_set_write_buffer($channel, $this->getMaxPackageSize() + 4096);

                    if ($secure) {
                        stream_set_blocking($channel, 1);
                        if (!@stream_socket_enable_crypto($channel, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
                            @fclose($channel);
                            return;
                        }
                    }
                    stream_set_blocking($channel, 0);
                    $this->process(new Stream($channel));
                });
            })->then(function () {
                echo " - Ready\n";
            }, function (\Throwable $ex) {
                echo "- Failed: {$ex->getMessage()}\n";
            })->await();
        }

        loop()->start();
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

    public function process(Stream $channel)
    {
        $this->trigger('connect', $channel);
        stream_set_blocking($channel, 0);
        attach($channel, function (Stream $stream) {
            $this->trigger('receive', $stream);
            defer(function () use ($stream) {
                $this->trigger('close', $stream)
                    ->finally(function () use ($stream) {
                        $resource = $stream->detach();
                        detach($resource);
                    });
            });
        });
    }
}
