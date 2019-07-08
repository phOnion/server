<?php
namespace Onion\Framework\Server;

use GuzzleHttp\Stream\StreamInterface;
use function Onion\Framework\EventLoop\attach;
use GuzzleHttp\Stream\BufferStream;
use function Onion\Framework\EventLoop\detach;

class Connection
{
    private $address;
    private $resource;

    private $crypto;

    public function __construct(StreamInterface $stream)
    {
        $resource = $stream->detach();
        $stream->attach($resource);
        $this->address = stream_socket_get_name($resource, true);
        $this->resource = $resource;

        $this->crypto = $stream->getMetadata('crypto');
        $this->buffer = new BufferStream();

        attach($stream, null, function (StreamInterface $stream) {
            $buffer = $this->buffer->getContents();
            set_error_handler(function (int $errno, string $errstr) {
                if (stripos($errstr, 'errno=' . SOCKET_EAGAIN) !== false) {
                    return null;
                }

                if (stripos($errstr, 'errno=' . SOCKET_EWOULDBLOCK) !== false) {
                    return null;
                }

                throw new \RuntimeException($errstr);
            });

            $bytes = @$stream->write($buffer);
            restore_error_handler();
            if ($bytes === false) {
                $this->close();
                return;
            }

            if ($bytes === 0) {
                return;
            }

            if ($bytes !== strlen($buffer)) {
                $this->buffer->write(substr($buffer, $bytes));
                return;
            }
        });
    }

    public function send(string $data): int
    {
        return $this->buffer->write($data);
    }

    public function fetch(int $size = 8192, int $flags): ?string
    {
        return stream_socket_recvfrom($this->resource, $size, $flags);
    }

    public function getContents(int $length = -1): string
    {
        return stream_get_contents($this->resource, $length);
    }

    public function close(): bool
    {
        return fclose($this->resource);
    }

    public function getId(): int
    {
        return (int) $this->resource;
    }

    public function isAvailable(): bool
    {
        return !feof($this->resource);
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function isEncrypted(): bool
    {
        return $this->crypto !== null;
    }

    public function getCryptoOption(string $name = null)
    {
        return ($this->crypto[$name] ?? null);
    }
}
