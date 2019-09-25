<?php
namespace Onion\Framework\Server\Listeners;

use Onion\Framework\Server\Events\ConnectEvent;

class CryptoListener
{
    private $mode;

    public function __construct(int $cryptoStream = STREAM_CRYPTO_METHOD_TLS_SERVER)
    {
        $this->mode = $cryptoStream;
    }

    public function __invoke(ConnectEvent $event)
    {
        $socket = $event->getConnection();
        $context = stream_context_get_options($socket->getDescriptor());
        if (isset($context['ssl'])) {
            while ($success = @stream_socket_enable_crypto(
                $socket->getDescriptor(),
                true,
                $this->mode,
                $socket->getDescriptor()
            ) === 0) {
                yield;
            }
        }
    }
}
