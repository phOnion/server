<?php
namespace Onion\Framework\Server;

use GuzzleHttp\Psr7\Response;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;

class HttpServer extends Server
{
    public function __construct()
    {
        parent::on('receive', function (Stream $stream) {
            try {
                $buffer = '';
                while (!$stream->eof()) {
                    usleep(1);
                    $data = $stream->read(-1);

                    if (strlen($buffer) !== 0 && strlen($data) === 0) {
                        break;
                    }

                    $buffer .= $data;
                }
                $request = build_request($buffer);
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
        });
    }

    protected function trigger(string $event, ...$args)
    {
        if ($event !== 'connect') {
            return parent::trigger($event, ...$args);
        }

        return new RejectedPromise(new \LogicException("Not allowed to trigger unsupported events"));
    }

    public function on(string $event, callable $callback)
    {
        if (strtolower($event) === 'receive') {
            throw new \RuntimeException(
                "Binding on 'receive' is not allowed for HTTP server"
            );
        }

        parent::on($event, $callback);
    }
}
