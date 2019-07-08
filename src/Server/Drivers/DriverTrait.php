<?php
namespace Onion\Framework\Server\Drivers;

use Onion\Framework\Loop\Socket;

trait DriverTrait
{
    protected function createSocket(string $interface, ?int $port, array $contexts)
    {
        $ctxOptions = [];
        foreach ($contexts as $context) {
            $ctxOptions = array_merge($ctxOptions, $context->getContextArray());
        }

        $socket = stream_socket_server(
            "{$this->getScheme($interface)}://{$interface}:{$port}",
            $error,
            $message,
            static::SOCKET_FLAGS,
            stream_context_create($ctxOptions)
        );

        if (!$socket) {
            throw new \RuntimeException($message, $error);
        }

        $socketObject = new Socket($socket);
        $socketObject->unblock();

        return $socketObject;
    }
}
