<?php
namespace Onion\Framework\Server\WebSocket;

use Onion\Framework\EventLoop\Stream\Interfaces\StreamInterface;
use Onion\Framework\Server\WebSocket\Exceptions\CloseException;
use Onion\Framework\Server\WebSocket\Exceptions\UnknownOpcodeException;

class Stream
{
    public const CODE_NORMAL_CLOSE = 1000;
    public const CODE_NOT_ACCEPTABLE = 1003;
    public const CODE_GOAWAY = 1001;

    private $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function write(Frame $frame): ?int
    {
        return $this->stream->write(Frame::encode($frame));
    }

    public function read(int $size = 8192): ?Frame
    {
        $data = $this->stream->read($size);

        if ($data === null) {
            return null;
        }

        $frame = Frame::unmask($data);

        switch ($frame->getOpcode()) {
            case Frame::OPCODE_CLOSE:
                $this->close($frame->getData());
                throw new CloseException("Received normal close signal", self::CODE_NORMAL_CLOSE);
                break;
            case Frame::OPCODE_PING:
                $this->ping($frame->getData());
            case Frame::OPCODE_PONG:
                return null;
                break;
            case Frame::OPCODE_TEXT:
            case Frame::OPCODE_BINARY:
                return $frame;
            default:
                throw new UnknownOpcodeException(
                    "Unknown opcode received ({$frame->getOpcode()})",
                    self::CODE_NOT_ACCEPTABLE
                );
                break;
        }
    }

    public function ping(string $text = '')
    {
        return $this->write(
            new Frame($text, Frame::OPCODE_PING)
        ) > 0;
    }

    public function pong(string $text = '')
    {
        return $this->write(
            new Frame($text, Frame::OPCODE_PONG)
        ) > 0;
    }

    public function close(string $reason = ''): bool
    {
        $this->write(
            new Frame($reason, Frame::OPCODE_CLOSE)
        );

        return $this->stream->close();
    }

    public function detach(): StreamInterface
    {
        $stream = $this->stream;
        $this->stream = null;

        return $stream;
    }
}
