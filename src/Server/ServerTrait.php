<?php
namespace Onion\Framework\Server;

use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;
use function Onion\Framework\Promise\async;

trait ServerTrait
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
                stream_set_blocking($socket, 0);

                if (!$socket) {
                    throw new \RuntimeException(
                        "Unable to bind on '{$address}' - {$errMessage} ({$errCode})",
                        $errCode
                    );
                }

                return $socket;
            })->otherwise(function (\Throwable $ex) {
                echo "Error ({$ex->getMessage()})\n";

                return $ex;
            });
        }

        return Promise::all($promises);
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

}
