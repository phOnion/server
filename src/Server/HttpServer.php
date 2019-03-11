<?php
namespace Onion\Framework\Server;

use GuzzleHttp\Psr7\Response;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;

class HttpServer extends TcpServer
{
    public function process(Stream $stream)
    {
        try {
            $request = build_request($stream, $this->getMaxPackageSize());
            if ($request->getHeaderLine('Content-Length') > $this->getMaxPackageSize()) {
                $promise = new Promise(function ($resolve) use ($request) {
                    $resolve(new Response($request->hasHeader('Expect') ? 417 : 413, [
                        'content-type' => 'text/plain',
                    ]));
                });
            } else {
                $promise = $this->trigger('request', $request)
                    ->otherwise(function (\Throwable $ex) {
                        return new Response(500, [
                            'Content-Type' => 'text/plain; charset=urf-8',
                        ], $ex->getMessage());
                    });
            }

            $promise->then(function (ResponseInterface $response) use ($stream) {
                send_response($response, $stream);

            })->finally(function () use ($stream) {
                $stream->close();
            });
        } catch (\RuntimeException $ex) {
            $stream->close();
        }
    }

    protected function trigger(string $event, ...$args)
    {
        if ($event !== 'connect' && $event !== 'receive') {
            return parent::trigger($event, ...$args);
        }

        return new RejectedPromise(new \LogicException("Not allowed to trigger unsupported events"));
    }
}
