<?php
namespace Onion\Framework\Server;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\detach;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Server\WebSocket\Exceptions\CloseException;
use Onion\Framework\Server\WebSocket\Exceptions\UnknownOpcodeException;
use Onion\Framework\Server\WebSocket\Stream as WebSocket;

class WebSocketServer extends HttpServer
{
    public function process(Stream $stream)
    {
        try {
            $request = $this->buildRequest($stream);

            if ($request->hasHeader('upgrade') && $request->getHeaderLine('upgrade') == 'websocket') {
                $this->trigger('handshake', $request, $stream)
                    ->then(function(WebSocket $socket) use ($request) {
                        $resource = $socket->detach()->detach();

                        $this->trigger('open', $request, new WebSocket(
                            new Stream($resource)
                        ));

                        attach($resource, function ($stream) {
                            $socket = new WebSocket($stream);

                            try {
                                if(($data = $socket->read($this->getMaxPackageSize())) !== null) {
                                    $this->trigger('message', $socket, $data);
                                }
                            } catch (CloseException $ex) {
                                if (!$socket->isClosed()) {
                                    $socket->close($ex->getCode());
                                }

                                $stream = $socket->detach();
                                $stream->close();
                                detach($stream->detach());

                                $this->trigger('close', $ex->getCode());
                            }
                        });
                    })->otherwise(function () {
                        $this->trigger('close', WebSocket::CODE_INTERNAL_ERROR);
                    });
            } else {
                parent::processRequest($request, $stream);
            }
        } catch (\Throwable $ex) {
            $stream->close();
        }
    }
}
