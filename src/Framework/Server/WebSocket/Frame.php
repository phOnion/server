<?php
namespace Onion\Framework\Server\WebSocket;

class Frame
{
    public const OPCODE_TEXT = 0x01;
    public const OPCODE_BINARY = 0x02;
    public const OPCODE_CLOSE = 0x08;
    public const OPCODE_PING = 0x09;
    public const OPCODE_PONG = 0x0A;

    public const OPCODE_CONTINUATION = 0x00;
    public const OPCODE_FINISHED = 0b10000000;

    private const OPCODE_READABLE_MAP = [
        -1 => 'UNKNOWN',
        0x01 => 'TEXT (0x01)',
        0x02 => 'BINARY (0x02)',
        0x08 => 'CLOSE (0x08)',
        0x09 => 'PING (0x09)',
        0x0A => 'PONG (0x0A)',
    ];

    private $data;
    private $opcode = -1;
    private $final;

    public function __construct(?string $data = null, int $opcode = self::OPCODE_TEXT, bool $final = true)
    {
        $this->data = $data;
        $this->opcode = $opcode;
        $this->final = $final;
    }

    public function getOpcode(): int
    {
        return $this->opcode;
    }

    public function isFinal(): bool
    {
        return $this->final === true;
    }

    public function getData(): string
    {
        return (string) $this->data;
    }

    public function __toString()
    {
        return $this->getData();
    }

    public static function encode(Frame $frame, bool $masked = false): string
    {
        $length = strlen($frame->getData());

        $opcode = $frame->getOpcode() | ($frame->isFinal() ? Frame::OPCODE_FINISHED : Frame::OPCODE_CONTINUATION);
        $mask = $masked ? 0b10000000 : 0;

        if ($length > 125 && $length < 65536) {
            $header = pack('CCS', $mask | $opcode, 126, $length);
        } elseif ($length >= 65536) {
            $header = pack('CCN', $mask | $opcode, 127, $length);
        } else {
            $header = pack('CC', $mask | $opcode, $length);
        }

        $data = $frame->getData();
        if ($masked) {
            $bytes = \random_bytes(4);
            $data = $bytes . ($data ^ \str_pad($bytes, $length, $bytes, \STR_PAD_RIGHT));
        }

        return $header.$data;
    }

    public static function unmask(string $packet): Frame
    {
        $opcode = ord($packet);
        $finished = ($opcode | Frame::OPCODE_FINISHED) === $opcode;

        $text = '';
        if (isset($packet[1])) {
            $length = ord($packet[1]) & 127;

            if ($length === 126) {
                $masks = substr($packet, 4, 4);
                $data = substr($packet, 8);
            } elseif ($length === 127) {
                $masks = substr($packet, 10, 4);
                $data = substr($packet, 14);
            } else {
                $masks = substr($packet, 2, 4);
                $data = substr($packet, 6);
            }

            for ($i = 0; $i < strlen($data); ++$i) {
                $text .= $data[$i] ^ $masks[$i%4];
            }
        }

        return new Frame(
            $text,
            $opcode ^ ($finished ? Frame::OPCODE_FINISHED : 0),
            $finished
        );
    }

    public function __debugInfo()
    {
        return [
            'opcode' => self::OPCODE_READABLE_MAP[$this->getOpcode()],
            'final' => $this->isFinal() ? 'Yes' : 'No',
            'size' => \strlen($this->getData()),
        ];
    }
}
