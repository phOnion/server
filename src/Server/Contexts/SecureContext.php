<?php
namespace Onion\Framework\Server\Contexts;

use Onion\Framework\Server\Interfaces\ContextInterface;

class SecureContext implements ContextInterface
{
    private $options = [
        'disable_compression' => true,
        'ciphers' => 'HIGH:!SSLv2:!SSLv3',
    ];

    public function setPeerName(string $name)
    {
        $this->options['peer_name'] = $name;
    }

    public function setVerifyPeer(bool $enable)
    {
        $this->options['verify_peer'] = $enable;
    }

    public function setVerifyPeerName(bool $enable)
    {
        $this->options['verify_peer_name'] = $enable;
    }

    public function setAllowSelfSigned(bool $enable)
    {
        $this->options['allow_self_signed'] = $enable;
    }

    public function setCaFile(string $file)
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException(
                "File '{$file}' does not exist"
            );
        }

        $this->options['cafile'] = $file;
    }

    public function setCaPath(string $dir)
    {
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException(
                "Directory '{$dir}' does not exist"
            );
        }

        $this->options['capath'] = $dir;
    }

    public function setLocalCert(string $file)
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException(
                "File '{$file}' does not exist"
            );
        }

        $this->options['local_cert'] = $file;
    }

    public function setLocalKey(string $file)
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException(
                "File '{$file}' does not exist"
            );
        }

        $this->options['local_pk'] = $file;
    }

    public function setLocalKeyPassphrase(string $passphrase)
    {
        $this->options['passphrase'] = $passphrase;
    }

    public function setVerifyDepth(int $depth)
    {
        $this->options['verify_depth'] = $depth;
    }

    public function setCiphers(string $ciphers)
    {
        $this->options['ciphers'] = $ciphers;
    }

    public function setPeerCertCapture(bool $enable)
    {
        $this->options['capture_peer_cert'] = $enable;
    }

    public function setPeerCertChainCapture(bool $enable)
    {
        $this->options['capture_peer_cert_chain'] = $enable;
    }

    public function setSniEnable(bool $enable)
    {
        $this->options['SNI_enabled'] = $enable;
    }

    public function setSniServerName(string $name)
    {
        $this->options['SNI_server_name'] = $name;
    }

    public function setDisableCompression(bool $enable)
    {
        $this->options['disable_compression'] = $enable;
    }

    public function setPeerFingerprint(array $fingerprints)
    {
        $this->options['peer_fingerprint'] = $fingerprints;
    }

    public function getContextArray(): array
    {
        return [
            'ssl' => $this->options,
        ];
    }

    public function getContextOptions(): array
    {
        return $this->options;
    }
}
