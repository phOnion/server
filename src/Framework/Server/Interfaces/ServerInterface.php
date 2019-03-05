<?php
namespace Onion\Framework\Server\Interfaces;

interface ServerInterface
{
    public const TYPE_TCP = 1;
    public const TYPE_UDP = 2;
    public const TYPE_SOCK = 4;
    public const TYPE_SECURE = 8;

    public function start();
    public function addListener(string $address, int $port, int $type);
    public function on(string $event, \Closure $callback);
    public function set(array $configuration);
}
