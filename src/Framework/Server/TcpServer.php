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

    public function __construct(string $interface, ?int $port = null, int $type = self::TYPE_SOCK)
    {
        $this->listeners[] = [
            $interface, $port, $type
        ];
    }

    public function addListener(string $interface, int $port, int $type = 0)
    {
        $this->listeners[] = [
            $interface, $port, $type
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
            list($interface, $port, $type)=$listener;

            async(function () use ($interface, $port, $type) {
                $address = null;
                $options = 0;

                if (($type & self::TYPE_SOCK) === self::TYPE_SOCK) {
                    $address = "unix://{$interface}";
                    $options = STREAM_SERVER_BIND |STREAM_SERVER_LISTEN;
                } else if (($type & self::TYPE_TCP) === self::TYPE_TCP) {
                    $address = "tcp://{$interface}:{$port}";
                    $options = STREAM_SERVER_BIND |STREAM_SERVER_LISTEN;
                } else if (($type & self::TYPE_UDP) === self::TYPE_UDP) {
                    $address = "udp://{$interface}:{$port}";
                    $options = STREAM_SERVER_BIND;
                } else {
                    throw new \RuntimeException(
                        "Unable to determine server type for '{$interface}:{$port}'"
                    );
                }

                $secure = ($type & self::TYPE_SECURE) === self::TYPE_SECURE;
                $context = stream_context_create();
                if ($secure) {
                    $this->getSecurityContext($context);
                }

                $socket = @stream_socket_server($address, $errCode, $errMessage, $options, $context);
                stream_set_blocking($socket, 0);

                if (!$socket) {
                    throw new \RuntimeException(
                        "Unable to bind on '{$address}' - {$errMessage}",
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

                    if ($secure) {
                        stream_set_blocking($channel, 1);
                        if (!@stream_socket_enable_crypto($channel, true, STREAM_CRYPTO_METHOD_TLS_SERVER)) {
                            detach($channel);
                            @fclose($channel);
                            return;
                        }
                    }
                    stream_set_blocking($channel, 0);
                    $this->process(new Stream($channel));
                });
            })->otherwise(function (\Throwable $ex) {
                echo "- Failed: {$ex->getMessage()}\n";
            })->finally(function () {
                echo " - Ready\n";
            });

        }

        loop()->start();
    }

    private function getSecurityContext($context)
    {
        $options = [
            'local_cert' => $this->configs['ssl_cert_file'] ?? null,
            'local_pk' => $this->configs['ssl_key_file'] ?? null,
            'verify_peer' => $this->configs['ssl_verify_peer'] ?? null,
            'allow_self_signed' => $this->configs['ssl_allow_self_signed'] ?? null,
            'verify_depth' => $this->configs['ssl_verify_depth'] ?? null,
            'cafile' => $this->configs['ssl_client_cert_file'] ?? null,
            'passphrase' => $this->configs['ssl_cert_passphrase'] ?? null,
        ];

        $options = array_filter($options, function($value) {
            return $value !== null;
        });

        foreach ($options as $key => $value) {
            stream_context_set_option($context, 'ssl', $key, $value);
        }
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
