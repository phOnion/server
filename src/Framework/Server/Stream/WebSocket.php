<?php
namespace Onion\Framework\Server\Stream;

use Onion\Framework\Server\Stream\Exceptions\CloseException;
use Onion\Framework\Server\Stream\Exceptions\UnknownOpcodeException;
use Onion\Framework\EventLoop\Stream\Interfaces\StreamInterface;

class WebSocket
{
    public const OPCODE_TEXT = 0x01;
    public const OPCODE_BINARY = 0x02;
    public const OPCODE_CLOSE = 0x08;
    public const OPCODE_PING = 0x09;
    public const OPCODE_PONG = 0x0A;

    public const OPCODE_CONTINUATION = 0x00;
    public const OPCODE_FINISHED = 0b10000000;

    private $stream;

    public function __construct(StreamInterface $stream)
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

    public function write($data, $opcode = self::OPCODE_TEXT | self::FINISHED): ?int
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
            case self::OPCODE_CLOSE | self::OPCODE_FINISHED:
                $reason = '';
                if (isset($data[1])) {
                    $reason = $this->unmask($data);
                }
                $this->close($reason);
                throw new CloseException();
                return null;
                break;
            case self::OPCODE_PING | self::OPCODE_FINISHED:
                $reason = $this->unmask($data);
                $this->ping($reason);
                return null;
                break;
            case self::OPCODE_PONG | self::OPCODE_FINISHED:
                echo "pong\n";
                return null;
                break;
            case self::OPCODE_TEXT | self::OPCODE_FINISHED:
            case self::OPCODE_BINARY | self::OPCODE_FINISHED:
                return $this->unmask($data);
            default:
                throw new UnknownOpcodeException();
                break;
        }
    }

    public function ping(string $text = '')
    {
        return $this->write(substr($text, 0, 125), self::OPCODE_PING | self::OPCODE_FINISHED) > 0;
    }

    public function pong(string $text = '')
    {
        return $this->write(substr($text, 0, 125), self::OPCODE_PONG | self::OPCODE_FINISHED) > 0;
    }

    public function close(string $reason = ''): bool
    {
        $this->write(substr($reason, 0, 125), self::OPCODE_CLOSE | self::OPCODE_FINISHED);
        return $this->stream->close();
    }

    public function detach(): StreamInterface
    {
        $stream = $this->stream;
        $this->stream = null;

        return $stream;
    }
}
