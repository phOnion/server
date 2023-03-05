<?php

namespace Onion\Framework\Server\Drivers\Types;

enum Scheme: string
{
    case TCP = 'tcp';
    case UNIX = 'unix';
    case UDP = 'udp';
    case UDG = 'udg';
}
