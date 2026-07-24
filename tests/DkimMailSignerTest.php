<?php

namespace Hounddd\MailDkim\Tests;

use Hounddd\MailDkim\Classes\DkimMailSigner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mime\Email;

class DkimMailSignerTest extends TestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'maildkim-tests-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function testSigningIsDisabledWhenConfigurationIsIncomplete(): void
    {
        $signer = new DkimMailSigner([
            'enabled' => true,
            'domain' => 'example.com',
            'selector' => 'dkim',
        ], new NullLogger());

        $email = $this->makeEmail();

        $result = $signer->signSymfonyEmail($email);

        $this->assertFalse($result);
        $this->assertFalse($email->getHeaders()->has('DKIM-Signature'));
    }

    public function testInvalidPrivateKeyDoesNotCrashAndReturnsFalse(): void
    {
        $keyPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'invalid.pem';
        file_put_contents($keyPath, 'not-a-valid-private-key');

        $signer = new DkimMailSigner([
            'enabled' => true,
            'private_key_path' => $keyPath,
            'domain' => 'example.com',
            'selector' => 'dkim',
            'algorithm' => 'rsa-sha256',
        ], new NullLogger());

        $email = $this->makeEmail();

        $result = $signer->signSymfonyEmail($email);

        $this->assertFalse($result);
        $this->assertFalse($email->getHeaders()->has('DKIM-Signature'));
    }

    public function testSuccessfulSigningAddsDkimSignatureHeader(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('OpenSSL extension is required for DKIM signing test.');
        }

        $keyPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'private.pem';
        file_put_contents($keyPath, $this->generateRsaPrivateKey());

        $signer = new DkimMailSigner([
            'enabled' => true,
            'private_key_path' => $keyPath,
            'domain' => 'example.com',
            'selector' => 'dkim',
            'algorithm' => 'rsa-sha256',
        ], new NullLogger());

        $email = $this->makeEmail();

        $result = $signer->signSymfonyEmail($email);

        $this->assertTrue($result);
        $this->assertTrue($email->getHeaders()->has('DKIM-Signature'));
    }

    protected function makeEmail(): Email
    {
        return (new Email())
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->subject('Test email')
            ->text('Hello from test suite.');
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
