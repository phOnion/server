<?php
require __DIR__ . '/../vendor/autoload.php';

use function Onion\Framework\EventLoop\select;
use GuzzleHttp\Stream\Stream;
use Onion\Framework\Server\Udp\Packet;


$sock = stream_socket_client('udp://127.0.0.1:1339', $error, $message, 30);
if (!$sock) {
    throw new \RuntimeException("{$message} ({$error})");
}
stream_set_blocking($sock, false);

$read = [$sock];
$write = [$sock];
$oob = [$sock];

echo "\tUDP\n";
if (select($read, $write, $obb, null)) {
    $stream = new Stream($sock);
    $address = stream_socket_get_name($sock, true);
    $d = new Packet($stream);
    for ($i=0; $i<3; $i++) {
        $d->send("#{$i}: " . time());
        echo "> {$d->read(128)}\n";
        sleep(1);
    }
}
unset($stream);

echo "\tTCP\n";

$sock = stream_socket_client('tcp://localhost:1337', $error, $message, 30);
if (!$sock) {
    throw new \RuntimeException("{$message} ({$error})");
}


$stream = new Stream($sock);

$read = [$sock];
$write = [$sock];
$oob = [$sock];

if (select($read, $write, $obb, null)) {
    $d = new Stream($sock);
    for ($i=0; $i<3; $i++) {
        $d->write("#{$i}: " . time());
        echo "> {$d->read(128)}\n";
        sleep(1);
    }
}


