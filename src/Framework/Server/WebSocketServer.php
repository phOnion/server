<?php
namespace Onion\Framework\Server;

use function Onion\Framework\EventLoop\attach;
use function Onion\Framework\EventLoop\detach;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Server\Stream\Exceptions\CloseException;
use Onion\Framework\Server\Stream\Exceptions\UnknownOpcodeException;
use Onion\Framework\Server\Stream\WebSocket;

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

                        $resource = $socket->detach();
                        detach($resource);
                        attach($resource, function ($stream) {
                            $socket = new WebSocket($stream->detach());

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
                    })->otherwise(function ($ex) use ($stream) {
                        $this->trigger('close');
                    });
            } else {
                parent::processRequest($request, $stream);
            }
        } catch (\Throwable $ex) {
            // var_dump($ex->getMessage());
            $stream->close();
        }
    }
}
