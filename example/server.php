<?php

use GuzzleHttp\Psr7\Response;
use Onion\Framework\EventLoop\Stream\Interfaces\StreamInterface as TcpStream;
use Onion\Framework\Server\Server as Server;
use Onion\Framework\Server\Udp\Interfaces\StreamInterface as UdpStream;
use Psr\Http\Message\ServerRequestInterface;
require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$server = new Server();
$server->addListener('0.0.0.0', 1337, Server::TYPE_TCP);
$server->addListener('0.0.0.0', 1338, Server::TYPE_TCP);
$server->addListener('0.0.0.0', 1339, Server::TYPE_UDP);

$server->on('start', function () {
    echo "Start\n";
});
// $server->set([
//     'ssl_cert_file' => __DIR__ . '/../localhost.cert',
//     'ssl_key_file' => __DIR__ . '/../localhost.key',
//     'ssl_allow_self_signed' => true,
//     'ssl_verify_peer' => false,
// ]);

// $server->on('request', function (ServerRequestInterface $request) {
//     $files = $request->getUploadedFiles();
//     if (count($files) > 0) {
//         return new Response(200, [
//             'content-type' => ['application/octet-stream'],
//             'content-disposition' => [
//                 'attachment; filename="test.jpeg"',
//             ],
//             'content-length' => [
//                 $files[0]->getSize()
//             ]
//         ], $files[0]->getStream());
//     }

//     return new Response(200, [
//         'content-type' => ['text/html'],
//     ], 'Hello, World!');
// });

$server->on('receive', function (TcpStream $stream, $data) {
    echo "Receive\n";

    $buffer = '';
    while ($stream->isClosed()) {
        $data = $stream->read();
        $buffer .= $data;
        $stream->write($buffer);
        if (strlen($buffer) !== 0 && strlen($data) === 0) {
            break;
        }
    }

    $stream->write($buffer . "Response");
});

$server->on('connect', function () {
    echo "Connected\n";
});

$server->on('close', function () {
    echo "Closed\n";
});

$server->on('packet', function (UdpStream $stream, $data, $address) {
    echo "Packet\n";
    $stream->write($stream->read(), $address);
});

$server->start();
