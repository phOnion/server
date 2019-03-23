<?php
require __DIR__ . '/../vendor/autoload.php';

use function Onion\Framework\EventLoop\select;
use Onion\Framework\EventLoop\Stream\Stream as TcpStream;
use Onion\Framework\Server\Udp\Stream;

$sock = stream_socket_client('udp://localhost:1339', $error, $message, 5);
if (!$sock) {
    throw new \RuntimeException("{$message} ({$error})");
}

$datagram = new Stream($sock);
$address = stream_socket_get_name($sock, true);
$datagram->write('test', $address);

$read = [$sock];
$write = [$sock];
$oob = [$sock];

echo "\tUDP\n";
if (select($read, $write, $obb, null)) {
    $d = new Stream($sock);
    if ($d->peek(1, false)) {
        echo "{$address}: " . $d->read(1500, !empty($obb), $address) . PHP_EOL;
    }
}

echo "\tTCP\n";

$sock = stream_socket_client('tcp://localhost:1338', $error, $message, 5);
if (!$sock) {
    throw new \RuntimeException("{$message} ({$error})");
}


$stream = new TcpStream($sock);


$read = [$sock];
$write = [$sock];
$oob = [$sock];

if (select($read, $write, $obb, null)) {
    $d = new TcpStream($sock);
    $d->write("test");
    echo "{$d->read()}\n";
}


