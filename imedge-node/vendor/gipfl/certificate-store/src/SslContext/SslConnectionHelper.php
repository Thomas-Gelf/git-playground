<?php

namespace gipfl\CertificateStore\SslContext;

use Exception;
use gipfl\CertificateStore\ClientStore\ClientSslStoreDirectory;
use gipfl\CertificateStore\Generator\KeyGenerator;
use Sop\CryptoEncoding\PEM;
use Sop\X509\Certificate\CertificateChain;

use function openssl_x509_export;

class SslConnectionHelper
{
    /**
     * @param resource $streamContextResource
     * @throws PeerCaDetectionError
     */
    public static function extractPeerCertificateChainFromResource($streamContextResource): CertificateChain
    {
        $p = stream_context_get_params($streamContextResource);
        $resourceChain = $p['options']['ssl']['peer_certificate_chain'] ?? null;

        if (empty($resourceChain)) {
            throw new PeerCaDetectionError('Unable to extract Certificate Chain');
        }

        $pemList = [];
        foreach ($resourceChain as $certResource) {
            try {
                $pemList[] = self::pemFromOpenSSLCertificate($certResource);
            } catch (Exception $e) {
                throw new PeerCaDetectionError('Unable to export X.509 certificate: ' . $e->getMessage());
            }
        }
        return CertificateChain::fromPEMs(...$pemList);
    }

    /**
     * @throws PeerCaDetectionError
     */
    protected static function pemFromOpenSSLCertificate(\OpenSSLCertificate $certificate): PEM
    {
        $string = null;
        if (! openssl_x509_export($certificate, $string)) {
            throw new PeerCaDetectionError('Unable to export certificate');
        }

        return PEM::fromString($string);
    }

    protected static function requireCertificate(ClientSslStoreDirectory $store, $certName)
    {
        if (!$store->hasPrivateKey($certName)) {
            $key = KeyGenerator::generate();
            $store->storePrivateKey($certName, $key);
        }
        if (! $store->hasCertificate($certName)) {
            $store->createSelfSignedCertificate($certName);
        }
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function getSslContextOptions(
        ClientSslStoreDirectory $certStore,
        string $certName,
        string $peerName
    ): array {
        return [
            'local_cert'           => $certStore->getCertificatePath($certName),
            'local_pk'             => $certStore->getPrivateKeyPath($certName),
            'peer_name'            => $peerName,
            'capture_session_meta' => true,
            'ciphers'              => 'HIGH',
        ] + self::getPeerVerificationOptions($certStore);
    }

    protected static function getPeerVerificationOptions(ClientSslStoreDirectory $store): array
    {
        if ($store->hasCaCertificate()) {
            return [
                'cafile'           => $store->getCaCertificatePath(),
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ];
        } else {
            return self::optionsWithoutCaFile();
        }
    }

    protected static function optionsWithoutCaFile(): array
    {
        return [
            'verify_peer'             => false,
            'verify_peer_name'        => true,
            'capture_peer_cert'       => true,
            'capture_peer_cert_chain' => true
        ];
    }
}
