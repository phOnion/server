<?php

use Onion\Framework\Event\Dispatcher;
use Onion\Framework\Event\ListenerProviders\AggregateProvider;
use Onion\Framework\Event\ListenerProviders\SimpleProvider;
use Onion\Framework\Server\Drivers\NetworkDriver;
use Onion\Framework\Server\Events\CloseEvent;
use Onion\Framework\Server\Events\ConnectEvent;
use Onion\Framework\Server\Events\MessageEvent;
use Onion\Framework\Server\Events\PacketEvent;
use Onion\Framework\Server\Events\StartEvent;
use Onion\Framework\Server\Listeners\CryptoListener;
use Onion\Framework\Server\Server as Server;

use function Onion\Framework\Loop\scheduler;
use Onion\Framework\Loop\Types\Operation;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$provider = new AggregateProvider();
$provider->addProvider(new SimpleProvider([
    StartEvent::class => [function () {
        echo "Started\n";
    }],
    ConnectEvent::class => [
        new CryptoListener,
        function () {
            echo "Connected\n";
        }
    ],
    CloseEvent::class => [function () {
        echo "Close\n";
    }],
    MessageEvent::class => [
        function (MessageEvent $ev) {
            echo "Message\n";
            $ev->connection->close();
        }
    ],
    PacketEvent::class => [
        function (PacketEvent $event) {
            var_dump($event->connection->read(8192));
            $event->connection->write('RECEIVED');
        },
    ],
]));
$dispatcher = new Dispatcher($provider);

$baseListener = new NetworkDriver($dispatcher);
// $secureCtx = new SecureContext;
// $secureCtx->setLocalCert(__DIR__ . '/localhost.cert');
// $secureCtx->setLocalKey(__DIR__ . '/localhost.key');
// $secureCtx->setAllowSelfSigned(true);
// $secureCtx->setVerifyPeer(false);
// $secureListener = new NetworkDriver($dispatcher);

// $udpDriver = new UdpDriver($dispatcher);

$server = new Server($dispatcher);

$driver = new NetworkDriver($dispatcher);
$server->attach($driver, 'tcp://0.0.0.0', 8080);
$server->attach($driver, 'udp://0.0.0.0', 12345);
// $server->attach($secureListener, '0.0.0.0', 8443, $secureCtx);
// $server->attach($udpDriver, '0.0.0.0', 12345);

$server->start();
scheduler()->start();
