<?php
namespace Onion\Framework\Server;

use function Onion\Framework\EventLoop\attach;
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
                        $this->trigger('open', $request);

                        attach($socket->detach()->detach(), function ($stream) {
                            $socket = new WebSocket($stream);

                            try {
                                $data = $socket->read();

                                if ($data) {
                                    $this->trigger('message', $socket, $data);
                                }
                            } catch (CloseException $ex) {
                                $this->trigger('close');
                            } catch (UnknownOpcodeException $ex) {
                                $socket->close();
                                $this->trigger('close');
                            }
                        });
                    })->otherwise(function (\Throwable $ex) {
                        $this->trigger('close');
                    });
            } else {
                parent::processRequest($request, $stream);
            }
        } catch (\Throwable $ex) {
            $stream->close();
        }
    }
}
