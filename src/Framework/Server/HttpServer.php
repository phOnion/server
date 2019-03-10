<?php
namespace Onion\Framework\Server;

use function GuzzleHttp\Psr7\parse_query;
use function GuzzleHttp\Psr7\parse_request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Promise\Promise;
use Onion\Framework\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class HttpServer extends TcpServer
{
    private const MIN_BLOCKING_THRESHOLD = 1024 * 768;

    public function process(Stream $stream)
    {
        return $this->processRequest(
            $this->buildRequest($stream),
            $stream
        );
    }

    protected function processRequest(ServerRequestInterface $request, Stream $stream)
    {
        try {
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
                $stream->write("HTTP/1.1 {$response->getStatusCode()} {$response->getReasonPhrase()}\n");
                foreach ($response->getHeaders() as $header => $headers) {
                    foreach ($headers as $value) {
                        $stream->write("{$header}: {$value}\n");
                    }
                }
                $body = $response->getBody();
                $size = $body->getSize();

                if (!$response->hasHeader('content-length')) {

                    if ($size > 0) {
                        $stream->write("Content-Length: {$size}\n");
                    }
                }

                if ($size > self::MIN_BLOCKING_THRESHOLD) {
                    $stream->block();
                }


                $body->rewind();
                $stream->write("\n");
                while (!$body->eof()) {
                    $stream->write($body->read(4096));
                }
                $stream->close();
            });
        } catch (\RuntimeException $ex) {
            $stream->close();
        }
    }

    protected function buildRequest(Stream $stream): ServerRequestInterface
    {
        $req = parse_request($stream->read($this->getMaxPackageSize()));
        $request = new ServerRequest(
            $req->getMethod(),
            $req->getUri(),
            $req->getHeaders(),
            $req->getBody(),
            $req->getProtocolVersion()
        );


        $pattern = '/^multipart\/form-data; boundary=(?P<boundary>.*)$/i';
        if (preg_match($pattern, $request->getHeaderLine('content-type'), $matches)) {
            $request = $this->getMultiPartRequest($request, $matches['boundary']);
        } else if (preg_match('/^application\/x-www-form-urlencoded/', $request->getHeaderLine('content-type'), $matches)) {
            $request = $request->withParsedBody(parse_query($req->getBody()));
        } else if (preg_match('/^application\/json/', $request->getHeaderLine('content-type'))) {
            $request = $request->withParsedBody(json_decode((string) $req->getBody(), true));
        }

        return $request;
    }

    private function getMultiPartRequest(ServerRequestInterface $request, string $boundary)
    {
        $parts = explode('--' . $boundary, (string) $request->getBody());
        $files = [];
        $parsed = [];

        foreach ($parts as $part) {
            $sections = explode ("\r\n\r\n", trim($part), 2);

            $mediaType = 'application/octet-stream';
            $filename = null;
            $name = null;

            foreach (explode("\r\n", $sections[0]) as $header) {
                if (preg_match('/^(?J)(?P<name>.*): (?P<value>.*)$/im', $header, $matches)) {
                    if ($matches['name'] === 'Content-Disposition') {
                        preg_match(
                            '/form-data; name=\"(?P<name>[^"]+)\"(?:; filename=\"(?P<filename>[^"]+)\")?/',
                            $matches['value'],
                            $names
                        );

                        if (isset($names['filename']) && $names['filename'] !== '' && $names['filename'] !== null) {
                            $filename = $names['filename'];
                        }

                        if (isset($names['name']) && $names['name'] !== ''&& $names['name'] !== null) {
                            $name = $names['name'];
                        }
                    }

                    if ($matches['name'] === 'Content-Type') {
                        $mediaType = $matches['value'];
                    }
                }
            }

            if ($filename === null) {
                $parsed[$name] = $sections[1] ?? '';
            } else {
                $file = fopen(tempnam(sys_get_temp_dir(), time()), 'w+b');
                $size = fwrite($file, $sections[1] ?? '');

                $files[] = new UploadedFile($file, $size, 0, $filename, $mediaType);
            }
        }

        return $request->withParsedBody($parsed)
            ->withUploadedFiles($files);
    }

    protected function trigger(string $event, ...$args)
    {
        if ($event !== 'connect' && $event !== 'receive') {
            return parent::trigger($event, ...$args);
        }

        return new RejectedPromise(new \LogicException("Not allowed to trigger unsupported events"));
    }
}
