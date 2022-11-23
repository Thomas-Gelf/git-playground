<?php

namespace IcingaDataNode;

use Evenement\EventEmitterTrait;
use gipfl\CertificateStore\ClientStore\ClientSslStoreDirectory;
use gipfl\CertificateStore\TrustStore\TrustStoreDirectory;
use React\Socket\SecureServer;
use React\Socket\ServerInterface;
use React\Socket\TcpServer;
use React\Stream\Util;

class NetworkSocket implements ServerInterface
{
    use EventEmitterTrait;

    protected ?SecureServer $server = null;

    public function __construct(
        protected readonly TrustStoreDirectory $trustStore,
        protected readonly ClientSslStoreDirectory $sslStore,
        protected readonly string $certName,
        protected readonly string $address,
        protected readonly int $port,
    ) {
    }

    public function run(): void
    {
        $this->listen();
    }

    protected function certLab()
    {

    }

    protected function listen(): void
    {
        $certificatePath = $this->sslStore->getAbsoluteCertificatePathByName($this->certName);
        $privateKeyPath = $this->sslStore->getAbsolutePrivateKeyPathByName($this->certName);
        $server = new TcpServer(sprintf('tcp://%s:%d', $this->address, $this->port));
        $server = new SecureServer($server, null, [
            'local_cert' => $certificatePath,
            'local_pk'   => $privateKeyPath,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_SERVER,
            'SNI_enabled' => true,
            // 'SNI_server_certs' => [ // Which certificate to pick when accepting SNI connections
            //     $this->certName => $certificatePath,
            // ],
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'verify_depth'      => 0,
            'security_level' => 4,
            'ciphers' => 'HIGH',
        ]);
        Util::forwardEvents($server, $this, ['connection' ,'error']);
        $this->server = $server;
    }
// Für Director: peer_fingerprint => string|array<'sha'|'md5', string>. When string: strlen(32) = md5, 40 = sha1
// openssl_x509_fingerprint(file_get_contents('/path/to/key.crt')),
    /* Slient:

    ‘verify_peer’ => true,
‘cafile’ => ‘/path/to/cafile.pem’,
‘CN_match’ => ‘example.com’,
            'SNI_enabled' => true,
    */

    public function shutdown(): void
    {
        $this->close();
    }

    public function getAddress(): array|string|null
    {
        return $this->server->getAddress();
    }

    public function pause(): void
    {
        $this->server->pause();
    }

    public function resume(): void
    {
        $this->server->resume();
    }

    public function close(): void
    {
        if ($this->server) {
            $this->server->close();
            $this->server = null;
        }
    }
}
