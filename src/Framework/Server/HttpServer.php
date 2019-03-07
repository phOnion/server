<?php
namespace Onion\Framework\Server;

use function GuzzleHttp\Psr7\parse_query;
use function GuzzleHttp\Psr7\parse_request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\UploadedFile;
use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Promise\RejectedPromise;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class HttpServer extends TcpServer
{
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
            $this->trigger('request', $request)
                ->otherwise(function (\Throwable $ex) {
                    return new Response(500, [
                        'Content-Type' => 'text/plain; charset=urf-8',
                    ], $ex->getMessage());
                })->then(function (ResponseInterface $response) use ($stream) {
                    $stream->write("HTTP/1.1 {$response->getStatusCode()} {$response->getReasonPhrase()}\n");
                    foreach ($response->getHeaders() as $header => $headers) {
                        foreach ($headers as $value) {
                            $stream->write("{$header}: {$value}\n");
                        }
                    }

                    if (!$response->hasHeader('content-length')) {
                        $size = $response->getBody()->getSize();
                        $stream->write("Content-Length: {$size}\n");
                    }

                    $stream->write("\n");
                    $stream->write("{$response->getBody()->getContents()}\n");
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
        $parts = explode($boundary, (string) $request->getBody());
        $files = [];
        $parsed = [];
        foreach ($parts as $part) {
            $lines = explode("\n", $part);

            $mediaType = 'application/octet-stream';
            $filename = uniqid(time(), true);
            $name = null;

            foreach ($lines as $index => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if ($line === '--') {
                    unset($lines[$index]);
                    continue;
                }

                if (preg_match('/^(?P<name>.*): (?P<value>.*)$/', $line, $header)) {
                    switch (strtolower($header['name'])) {
                        case 'content-disposition':
                            preg_match(
                                '/form-data; (filename=\"(?P<filename>.*)\"|name=\"(?P<name>[^"]+)\"(?!\; filename=.*))/',
                                $header['value'],
                                $names
                            );
                            if (isset($names['filename']) && $names['filename'] !== '' && $names['filename'] !== null) {
                                $filename = $names['filename'];
                            } else if (isset($names['name']) && $names['name'] !== ''&& $names['name'] !== null) {
                                $name = $names['name'];
                            }
                            break;
                        case 'content-type':
                            $mediaType = $header['value'];
                            break;
                    }
                    unset($lines[$index]);
                }
            }

            if ($name !== null) {
                $parsed[$name] = trim(implode("\n", $lines));
            } else {
                $file = tmpfile();
                $size = fwrite($file, trim(implode("\n", $lines)));

                if ($size === 0) {
                    continue;
                }

                $files[] = new UploadedFile(
                    $file, $size, 0, $filename, $mediaType
                );
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
