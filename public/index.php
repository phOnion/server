<?php

use GuzzleHttp\Psr7\Response;
use Onion\Framework\Server\HttpServer as Server;
use Psr\Http\Message\ServerRequestInterface;
require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$server = new Server('0.0.0.0', 1337, Server::TYPE_TCP);
$server->addListener('0.0.0.0', 1338, Server::TYPE_TCP | Server::TYPE_SECURE);

$server->on('start', function () {
    echo "Start\n";
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
    ], 'Hello, World!');
});

$server->start();
