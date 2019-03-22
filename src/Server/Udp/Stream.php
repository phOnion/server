<?php
namespace Onion\Framework\Server\Udp;

use Onion\Framework\EventLoop\Stream\Stream as BaseStream;
use Onion\Framework\Server\Udp\Interfaces\StreamInterface;

class Stream extends BaseStream implements StreamInterface
{
    private $resource;

    public function __construct($resource)
    {
        $this->resource = $resource;
        parent::__construct($resource);
    }

    public function peek(int $size, bool $obb, string &$address = null): string
    {
        return stream_socket_recvfrom($this->resource, $size, ($obb ? STREAM_OBB : 0) | STREAM_PEEK, $address);
    }

    public function read(int $size = 8192, bool $obb = false, string &$address = null): string
    {
        return stream_socket_recvfrom($this->resource, $size, ($obb ? STREAM_OOB : 0), $address);
    }

    public function write(string $data, ?string $address = null, bool $obb = false): int
    {
        return stream_socket_sendto($this->resource, $data, ($obb ? STREAM_OBB : 0), $address);
    }

    public function close(): bool
    {
        return true;
    }
}
