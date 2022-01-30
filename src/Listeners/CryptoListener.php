<?php

namespace Onion\Framework\Server\Listeners;

use Onion\Framework\Server\Events\ConnectEvent;

use function Onion\Framework\Loop\tick;

class CryptoListener
{
    private int $mode;

    public function __construct(int $cryptoStream = STREAM_CRYPTO_METHOD_TLS_SERVER)
    {
        $this->mode = $cryptoStream;
    }

    public function __invoke(ConnectEvent $event)
    {
        $socket = $event->connection;
        $context = stream_context_get_options($socket->getResource());
        if (isset($context['ssl'])) {
            while (
                @stream_socket_enable_crypto(
                    $socket->getResource(),
                    true,
                    $this->mode,
                    $socket->getResource()
                ) === 0
            ) {
                tick();
            }
        }
    }
}
