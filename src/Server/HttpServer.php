<?php
namespace Onion\Framework\Server;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Stream\StreamInterface;
use Onion\Framework\Promise\Interfaces\PromiseInterface;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Promise\RejectedPromise;
use Onion\Framework\Server\Interfaces\ServerInterface;
use Psr\Http\Message\ResponseInterface;

class HttpServer extends Server implements ServerInterface
{
    public function __construct()
    {
        parent::on('connect', function () {});
        parent::on('close', function () {});
        parent::on('receive', function (StreamInterface $stream) {
            try {
                $request = build_request($stream->getContents());
                if ($request->getHeaderLine('Content-Length') > parent::getMaxPackageSize()) {
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

    protected function trigger(string $event, ...$args): PromiseInterface
    {
        if (strtolower($event) !== 'connect' || strtolower($event) !== 'receive') {
            return parent::trigger($event, ...$args);
        }

        return new RejectedPromise(new \LogicException("Not allowed to trigger unsupported events ({$event})"));
    }

    public function on(string $event, callable $callback): void
    {
        if (strtolower($event) === 'receive' || strtolower($event) === 'connect') {
            throw new \RuntimeException(
                "Binding on 'receive' is not allowed for HTTP server"
            );
        }

        parent::on($event, $callback);
    }

    public function addListener(string $address, ?int $port = 0, int $type = 0, array $options = []): void
    {
        if (($type & self::TYPE_UDP) === self::TYPE_UDP) {
            throw new \InvalidArgumentException(
                "Unable to add UDP listener to HTTP server"
            );
        }

        parent::addListener($address, $port, $type, $options);
    }
}
