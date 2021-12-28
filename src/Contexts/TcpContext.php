<?php

namespace Onion\Framework\Server\Contexts;

use Onion\Framework\Server\Interfaces\ContextInterface;

class TcpContext implements ContextInterface
{
    private array $options = [];

    public function setBindTo(string $address, ?int $port = null): void
    {
        if ($port !== null) {
            $address .= "{$address}:{$port}";
        }

        $this->options['bindto'] = $address;
    }

    public function setBacklog(int $count): void
    {
        $this->options['backlog'] = $count;
    }

    public function setIpV6Only(bool $enable): void
    {
        $this->options['ipv6_v6only'] = $enable;
    }

    public function setReusePort(bool $enable): void
    {
        $this->options['so_reuseport'] = $enable;
    }

    public function setBroadcast(bool $enable): void
    {
        $this->options['so_broadcast'] = $enable;
    }

    public function setNoDelay(bool $enable): void
    {
        $this->options['so_nodelay'] = $enable;
    }

    public function getContextArray(): array
    {
        return [
            'socket' => $this->options,
        ];
    }

    public function getContextOptions(): array
    {
        return $this->options;
    }
}
