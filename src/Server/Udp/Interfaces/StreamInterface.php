<?php
namespace Onion\Framework\Server\Udp\Interfaces;

use Onion\Framework\EventLoop\Stream\Interfaces\StreamInterface as BaseStream;

interface StreamInterface extends BaseStream
{
    public function peek(int $size = 8192, bool $obb, string &$address = null): string;
    public function read(int $size = 8192, bool $obb = false, string &$address = null): string;
    public function write(string $data, ?string $address = null, bool $obb = false): int;
}
