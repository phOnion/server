<?php
namespace Onion\Framework\Server\Stream;

use Onion\Framework\EventLoop\Stream\Stream;
use Onion\Framework\Server\Stream\Exceptions\CloseException;
use Onion\Framework\Server\Stream\Exceptions\UnknownOpcodeException;

class WebSocket
{
    public const OPCODE_TEXT = 129;
    public const OPCODE_BINARY = 130;
    public const OPCODE_CLOSE = 136;
    public const OPCODE_PING = 137;
    public const OPCODE_PONG = 138;

    private $stream;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;
    }

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

    private function unmask(string $payload): string
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
        return $this->stream->write($this->encode($data, $opcode));
    }

    public function read(int $size = 8192): ?string
    {
        $data = $this->stream->read($size);

        if ($data === null) {
            return null;
        }

        switch (ord($data)) {
            case self::OPCODE_CLOSE:
                $reason = '';
                if (isset($data[1])) {
                    $reason = $this->unmask($data);
                }
                parent::close($reason);
                throw new CloseException();
                return null;
                break;
            case self::OPCODE_PING:
                $reason = $this->unmask($data);
                $this->ping($reason);
                return null;
                break;
            case self::OPCODE_PONG:
                echo "pong\n";
                return null;
                break;
            case self::OPCODE_TEXT:
            case self::OPCODE_BINARY:
                return $this->unmask($data);
            default:
                throw new UnknownOpcodeException();
                break;
        }
    }

    public function ping(string $text = '')
    {
        return $this->write(substr($text, 0, 125), self::OPCODE_PING) > 0;
    }

    public function pong(string $text = '')
    {
        return $this->write(substr($text, 0, 125), self::OPCODE_PONG) > 0;
    }

    public function close(string $reason = ''): bool
    {
        $this->write(substr($reason, 0, 125), self::OPCODE_CLOSE);
        return parent::close();
    }

    public function detach(): Stream
    {
        $stream = $this->stream;
        $this->stream = null;

        return $stream;
    }
}
