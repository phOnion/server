<?php

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Stream\StreamInterface as TcpStream;
use Onion\Framework\Server\Server as Server;
use Onion\Framework\Server\Udp\Packet;
use Psr\Http\Message\ServerRequestInterface;
require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$server = new Server();
$server->addListener('0.0.0.0', 1337, Server::TYPE_TCP);
$server->addListener('0.0.0.0', 1338, Server::TYPE_TCP | Server::TYPE_SECURE, [
    'ssl_cert_file' => __DIR__ . '/../localhost.cert',
    'ssl_key_file' => __DIR__ . '/../localhost.key',
    'ssl_allow_self_signed' => true,
    'ssl_verify_peer' => false,
]);
$server->addListener('0.0.0.0', 1339, Server::TYPE_UDP);

$server->on('start', function () {
    echo "\nStart\n\r\n\r";
});

// $server->on('request', function (ServerRequestInterface $request) {
//     return new Response(200, [
//         'content-type' => ['text/html'],
//     ], 'Hello, World!');
// });

$server->on('receive', function (TcpStream $stream) {
    $resource = $stream->detach();
    $stream->attach($resource);

    $buffer = $stream->getContents();
    echo "< {$buffer}\n\r";
    $stream->write($buffer);
});

$server->on('connect', function () {
    // after(1000, function () {
    //     var_dump('n');
    //     echo "Timer tick\n\r\n\r";
    // });
    echo "\nConnected\n\r\n\r";
});

$server->on('close', function () {
    echo "\nClosed\n\r\n\r";
});

$server->on('packet', function (Packet $packet, $address) {
    $data = $packet->read(1024, $address);
    echo "< {$data}\n\r\n\r";
    $packet->send($data, $address);
});

$server->start();
