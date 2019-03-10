<?php

use function GuzzleHttp\Psr7\stream_for;
use function Onion\Framework\EventLoop\after;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\UploadedFile;
use Onion\Framework\Server\WebSocket\Frame;
use Onion\Framework\Server\WebSocket\Stream as WebSocket;
use Onion\Framework\Server\WebSocketServer as Server;
use Onion\Framework\Server\WebSocketServer;
use Psr\Http\Message\ServerRequestInterface;
require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$server = new WebSocketServer('0.0.0.0', 1337, Server::TYPE_TCP);
// $server->addListener('0.0.0.0', 1337, Server::TYPE_TCP);
// $server->addListener('0.0.0.0', 2346, Server::TYPE_TCP | Server::TYPE_SECURE);

// $server->on('request', function (ServerRequestInterface $request) {
//     throw new \Exception('Test');
// });
$server->on('connect', function () {
    echo "Connect\n";
});
$server->on('start', function () {
    echo "Start\n";
});
$server->on('open', function () {
    echo "Open\n";
});
$server->on('handshake', function (ServerRequestInterface $request, $stream) {
    $secWebSocketKey = $request->getHeaderLine('sec-websocket-key');
    $pattern = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

    if (0 === preg_match($pattern, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
        $stream->close();
        return false;
    }

    $key = base64_encode(sha1(
        $request->getHeaderLine('sec-websocket-key') . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
        true
    ));

    $stream->write("HTTP/1.1 101 Switching Protocols\n");
    $stream->write("Upgrade: websocket\n");
    $stream->write("Connection: Upgrade\n");
    $stream->write("Sec-WebSocket-Accept: {$key}\n");
    if ($request->hasHeader('Sec-WebSocket-Protocol')) {
        $stream->write("Sec-WebSocket-Protocol: {$request->getHeaderLine('sec-websocket-protocol')}\n");
    }
    $stream->write("Sec-WebSocket-Version: 13\n\n");

    return new WebSocket($stream);
});

$server->on('message', function (WebSocket $stream, $data) {
    echo "Message\n";
    $stream->write(
        new Frame($data, Frame::OPCODE_BINARY, true)
    );
});
$server->on('close', function () {
    echo "Close\n";
});
$server->set([
    'ssl_cert_file' => __DIR__ . '/../localhost.cert',
    'ssl_key_file' => __DIR__ . '/../localhost.key',
    'ssl_allow_self_signed' => true,
    'ssl_verify_peer' => false,
]);

$server->on('request', function (ServerRequestInterface $request) {
    $files = $request->getUploadedFiles();
    if (count($files) > 0) {
        return new Response(200, [
            'content-type' => ['application/octet-stream'],
            'content-disposition' => [
                'attachment; filename="test.jpeg"',
            ],
            'content-length' => [
                $files[0]->getSize()
            ]
        ], $files[0]->getStream());
    }

    return new Response(200, [
        'content-type' => ['text/html'],
    ], stream_for(fopen(__DIR__ . '/socket.html', 'r')));
});

$server->start();
