<?php
namespace Onion\Framework\Server\Listeners;

use Onion\Framework\Server\Events\ConnectEvent;

class CryptoListener
{
    public function __invoke(ConnectEvent $event)
    {
        $socket = $event->getConnection();
        $context = stream_context_get_options($socket->getDescriptor());
        if (isset($context['ssl'])) {
            $socket->block();
            if (!@stream_socket_enable_crypto(
                $socket->getDescriptor(),
                true,
                STREAM_CRYPTO_METHOD_ANY_SERVER,
                $socket->getDescriptor()
            )) {
                $socket->close();
            } else {
                $socket->unblock();
            }
        }
    }
}
