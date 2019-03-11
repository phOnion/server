<?php
namespace Onion\Framework\Server;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\detach;
use function Onion\Framework\EventLoop\loop;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Server\Interfaces\ServerInterface;

class TcpServer implements ServerInterface
{
    use ServerTrait;

    public function __construct(string $interface, ?int $port = null, int $type = self::TYPE_SOCK, array $options = [])
    {
        $this->addListener($interface, $port, $type, $options);
    }

    public function start()
    {
        $this->init()
            ->then(function ($sockets) {
                foreach ($sockets as $socket) {
                    stream_set_blocking($socket, 0);

                    $secure = (bool) (stream_context_get_options($socket)['ssl'] ?? false);

                    attach($socket, function (Stream $stream) use ($secure) {

                        $sock = $stream->detach();
                        $channel = @stream_socket_accept($sock);
                        stream_set_read_buffer($channel, $this->getMaxPackageSize() + 8192);

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
                }

                return $sockets;
            })->then(function ($sockets) {
                foreach ($sockets as $socket) {
                    echo "Server " . stream_socket_get_name($socket, false) . " - Ready\n";
                }

                $this->trigger('start');
            });

        loop()->start();
    }

    public function process(Stream $channel)
    {
        $this->trigger('connect', $channel);
        stream_set_blocking($channel, 0);
        attach($channel, function (Stream $stream) {
            if ($stream->isClosed()) {
                $stream->close();
                detach($stream->detach());
                $this->trigger('close');
                return;
            }

            $this->trigger('receive', $stream, $stream->read($this->getMaxPackageSize()));
        });
    }
}
