<?php
namespace Onion\Framework\Server;

use GuzzleHttp\Stream\StreamInterface;

class Connection
{
    private $address;
    private $resource;

    public function __construct(StreamInterface $stream)
    {
        $resource = $stream->detach();
        $this->address = stream_socket_get_name($resource, true);
        $this->resource = $resource;

        $stream->attach($resource);
    }

    public function send(string $data, int $flags = null): int
    {
        return stream_socket_sendto($this->resource, $data, $flags, $this->address);
    }

    public function fetch(int $size = 8192, int $flags): ?string
    {
        return stream_socket_recvfrom($this->resource, $size, $flags);
    }

    public function close(): bool
    {
        return fclose($this->resource);
    }

    public function getAddress(): string
    {
        return $this->address;
    }
}
