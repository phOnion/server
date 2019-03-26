<?php
namespace Onion\Framework\Server\Udp;

use GuzzleHttp\Stream\StreamInterface;

class Packet
{
    private $resource;

    public function __construct(StreamInterface $stream)
    {
        $resource = $stream->detach();
        $stream->attach($resource);
        $this->resource = $resource;
    }

    public function read(int $size, &$address = null, int $flags = null): string
    {
        return stream_socket_recvfrom($this->resource, $size, $flags, $address);
    }

    public function send(string $data, string $address, bool $oob = false): int
    {
        return stream_socket_sendto($this->resource, $data, ($oob ? STREAM_OOB : null), $address);
    }

    public function getAddress(bool $peer = false)
    {
        return stream_socket_get_name($this->resource, $peer);
    }
}
