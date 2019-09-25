<?php

use Onion\Framework\Event\Dispatcher;
use Onion\Framework\Event\ListenerProviders\AggregateProvider;
use Onion\Framework\Event\ListenerProviders\SimpleProvider;
use Onion\Framework\Loop\Interfaces\AsyncResourceInterface;
use Onion\Framework\Loop\Scheduler;
use Onion\Framework\Server\Contexts\SecureContext;
use Onion\Framework\Server\Drivers\TcpDriver;
use Onion\Framework\Server\Drivers\UdpDriver;
use Onion\Framework\Server\Events\CloseEvent;
use Onion\Framework\Server\Events\ConnectEvent;
use Onion\Framework\Server\Events\MessageEvent;
use Onion\Framework\Server\Events\PacketEvent;
use Onion\Framework\Server\Events\StartEvent;
use Onion\Framework\Server\Listeners\CryptoListener;
use Onion\Framework\Server\Server as Server;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$provider = new AggregateProvider();
$provider->addProvider(new SimpleProvider([
    StartEvent::class => [function () { echo "Started\n"; }],
    ConnectEvent::class => [
        new CryptoListener,
        function () { echo "Connected\n"; }
    ],
    CloseEvent::class => [function () { echo "Close\n"; }],
    MessageEvent::class => [function (MessageEvent $event) {
        $buffer = $event->getConnection();

        $message = "Message: {$buffer->read(8192)}";
        $length = strlen($message);

        yield $buffer->wait(AsyncResourceInterface::OPERATION_WRITE);
        $buffer->write("HTTP/1.1 200 OK\r\nContent-Length: {$length}\r\n\r\n{$message}\r\n");
    }],
    PacketEvent::class => [
        function (PacketEvent $event) {
            var_dump($event->getConnection()->read(8192));
        }
    ]
]));
$dispatcher = new Dispatcher($provider);

$baseListener = new TcpDriver($dispatcher);
$secureCtx = new SecureContext;
$secureCtx->setLocalCert(__DIR__ . '/localhost.cert');
$secureCtx->setLocalKey(__DIR__ . '/localhost.key');
$secureCtx->setAllowSelfSigned(true);
$secureCtx->setVerifyPeer(false);

$secureListener = new TcpDriver($dispatcher);

$udpDriver = new UdpDriver($dispatcher);

$server = new Server($dispatcher);

$server->attach($baseListener, '0.0.0.0', 1337);
$server->attach($secureListener, '0.0.0.0', 8443, $secureCtx);
$server->attach($udpDriver, '0.0.0.0', 12345);

$scheduler = new Scheduler;
$scheduler->add($server->start());
$scheduler->start();
