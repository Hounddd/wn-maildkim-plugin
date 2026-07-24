<?php

namespace Hounddd\MailDkim\Tests;

use Hounddd\MailDkim\Classes\DkimDiagnostics;
use Hounddd\MailDkim\Classes\DkimMailSigner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DkimDiagnosticsTest extends TestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maildkim-diagnostics-tests-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function testResolveDnsHostUsesConfiguredSelectorAndDomain(): void
    {
        $diagnostics = $this->makeDiagnostics([
            'domain' => 'example.com',
            'selector' => 'dkim',
        ]);

        $this->assertSame(
            'dkim._domainkey.example.com',
            $diagnostics->resolveDnsHost()
        );
    }

    public function testResolveDnsHostAcceptsOverrides(): void
    {
        $diagnostics = $this->makeDiagnostics([
            'domain' => 'example.com',
            'selector' => 'dkim',
        ]);

        $this->assertSame(
            'mail._domainkey.example.org',
            $diagnostics->resolveDnsHost('mail', 'example.org')
        );
    }

    public function testResolveDomainFallsBackToAppUrlHost(): void
    {
        $diagnostics = $this->makeDiagnostics([
            'app_url' => 'https://sub.example.net/some/path',
        ]);

        $this->assertSame('sub.example.net', $diagnostics->resolveDomain());
    }

    public function testExtractDnsPublicKeyRemovesWhitespace(): void
    {
        $diagnostics = $this->makeDiagnostics();

        $this->assertSame(
            'ABCDEF123456',
            $diagnostics->extractDnsPublicKey("v=DKIM1; k=rsa; p=ABC DEF\n123456")
        );
    }

    public function testPublicKeyAndDnsRecordCanBeDerivedFromPrivateKey(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension is required for DKIM diagnostics tests.');
        }

        $keyPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'private.pem';
        file_put_contents($keyPath, $this->generateRsaPrivateKey());

        $diagnostics = $this->makeDiagnostics([
            'private_key_path' => $keyPath,
            'passphrase' => '',
        ]);

        $publicKeyPem = $diagnostics->publicKeyPemFromPrivateKey();
        $publicKey = $diagnostics->expectedPublicKeyFromPrivateKey();
        $dnsRecordValue = $diagnostics->buildDnsRecordValue();

        $this->assertStringContainsString('BEGIN PUBLIC KEY', $publicKeyPem);
        $this->assertNotSame('', $publicKey);
        $this->assertSame(
            sprintf('v=DKIM1; k=rsa; p=%s', $publicKey),
            $dnsRecordValue
        );
    }

    public function testPublicKeyDerivationReturnsEmptyStringWhenPrivateKeyIsInvalid(): void
    {
        $keyPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'invalid.pem';
        file_put_contents($keyPath, 'not-a-valid-private-key');

        $diagnostics = $this->makeDiagnostics([
            'private_key_path' => $keyPath,
            'passphrase' => '',
        ]);

        $this->assertSame('', $diagnostics->publicKeyPemFromPrivateKey());
        $this->assertSame('', $diagnostics->expectedPublicKeyFromPrivateKey());
        $this->assertSame('', $diagnostics->buildDnsRecordValue());
    }

    protected function makeDiagnostics(array $config = []): DkimDiagnostics
    {
        $signer = new DkimMailSigner($config, new NullLogger());

        return new DkimDiagnostics($config, $signer);
    }

    protected function generateRsaPrivateKey(): string
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($resource === false) {
            $this->markTestSkipped('OpenSSL is available but cannot generate RSA keys in this environment.');
        }

        $privateKey = '';
        $exported = openssl_pkey_export($resource, $privateKey);

        if ($exported !== true) {
            $this->markTestSkipped('OpenSSL generated a key resource but could not export it in this environment.');
        }

        return $privateKey;
    }

    protected function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->deleteDirectory($fullPath);
                continue;
            }

            @unlink($fullPath);
        }

        @rmdir($path);
    }
}
