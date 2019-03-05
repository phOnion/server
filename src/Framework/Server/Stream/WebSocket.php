<?php
namespace Onion\Framework\Server\Stream;

use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Server\Stream\Exceptions\CloseException;

class WebSocket extends Stream
{
    public const OPCODE_TEXT = 129;
    public const OPCODE_BINARY = 130;
    public const OPCODE_CLOSE = 136;
    public const OPCODE_PING = 137;
    public const OPCODE_PONG = 138;

    private function encode($text, $opcode = self::OPCODE_TEXT): string
    {
        $length = strlen($text);

        if ($length > 125 && $length < 65536) {
            $header = pack('CCS', $opcode, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCN', $opcode, 127, $length);
        } else {
            $header = pack('CC', $opcode, $length);
        }

        return $header.$text;
    }

    public function unmask(string $payload): string
    {
        $length = ord($payload[1]) & 127;

        if ($length === 126) {
            $masks = substr($payload, 4, 4);
            $data = substr($payload, 8);
        } elseif ($length === 127) {
            $masks = substr($payload, 10, 4);
            $data = substr($payload, 14);
        } else {
            $masks = substr($payload, 2, 4);
            $data = substr($payload, 6);
        }

        $text = '';

        for ($i = 0; $i < strlen($data); ++$i) {
            $text .= $data[$i] ^ $masks[$i%4];
        }

        return $text;
    }

    public function write($data, $opcode = self::OPCODE_TEXT): ?int
    {
        return parent::write($this->encode($data, $opcode));
    }

    public function read(int $size = 8192): ?string
    {
        $data = parent::read($size);

        if ($data === null) {
            return null;
        }

        switch (ord($data)) {
            case self::OPCODE_CLOSE:
                throw new CloseException();
                break;
            case self::OPCODE_PING:
                $reason = $this->unmask($data);
                $this->ping($reason);
                return null;
                break;
            case self::OPCODE_PONG:
                return null;
                break;
            case self::OPCODE_TEXT:
            case self::OPCODE_BINARY:
                return $this->unmask($data);
            default:
                $this->close();
                throw new CloseException();
                break;
        }
    }

    public function ping(string $text = null)
    {
        return parent::write($this->encode(substr((string) $text, 0, 125), self::OPCODE_PING));
    }

    public function pong(string $text = null)
    {
        return parent::write($this->encode(substr((string) $text, 0, 125), self::OPCODE_PONG));
    }

    public function close(): bool
    {
        $this->write('', self::OPCODE_CLOSE);
        return parent::close();
    }
}