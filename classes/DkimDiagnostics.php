<?php

namespace Hounddd\MailDkim\Classes;

/**
 * Provides reusable diagnostic checks for DKIM configuration and DNS.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
class DkimDiagnostics
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected DkimMailSigner $signer;

    /**
     * Creates a diagnostics service from plugin config and signer.
     *
     * @param array<string, mixed> $config Plugin configuration values.
     * @param DkimMailSigner       $signer DKIM signer service.
     */
    public function __construct(array $config, DkimMailSigner $signer)
    {
        $this->config = $config;
        $this->signer = $signer;
    }

    /**
     * Returns blocking configuration issues from signer validation.
     *
     * @return array<int, string>
     */
    public function configurationIssues(): array
    {
        return $this->signer->getConfigurationIssues();
    }

    /**
     * Returns the configured or inferred DKIM domain.
     *
     * @return string|null
     */
    public function resolveDomain(): ?string
    {
        $configuredDomain = trim((string) ($this->config['domain'] ?? ''));
        if ($configuredDomain !== '') {
            return $configuredDomain;
        }

        $appUrl = trim((string) ($this->config['app_url'] ?? ''));
        if ($appUrl === '') {
            return null;
        }

        $host = parse_url($appUrl, PHP_URL_HOST);
        if (!is_string($host) || trim($host) === '') {
            return null;
        }

        return $host;
    }

    /**
     * Returns the configured DKIM selector.
     *
     * @return string
     */
    public function resolveSelector(): string
    {
        return trim((string) ($this->config['selector'] ?? 'dkim'));
    }

    /**
     * Resolves configured private key path.
     *
     * @return string
     */
    public function resolvePrivateKeyPath(): string
    {
        return trim((string) ($this->config['private_key_path'] ?? ''));
    }

    /**
     * Resolves full DNS host for DKIM TXT lookup.
     *
     * @param string      $selector Selector override.
     * @param string|null $domain   Domain override.
     *
     * @return string|null
     */
    public function resolveDnsHost(string $selector = '', ?string $domain = null): ?string
    {
        $resolvedSelector = trim($selector !== '' ? $selector : $this->resolveSelector());
        $resolvedDomain = trim((string) ($domain ?? $this->resolveDomain() ?? ''));

        if ($resolvedSelector === '' || $resolvedDomain === '') {
            return null;
        }

        return sprintf('%s._domainkey.%s', $resolvedSelector, $resolvedDomain);
    }

    /**
     * Reads DKIM TXT record(s) for a selector/domain pair.
     *
     * @param string      $selector DKIM selector.
     * @param string|null $domain   DKIM domain.
     *
     * @return array{ok: bool, host: string|null, values: array<int, string>, message: string}
     */
    public function lookupDnsRecord(string $selector = '', ?string $domain = null): array
    {
        $host = $this->resolveDnsHost($selector, $domain);
        if ($host === null) {
            return [
                'ok' => false,
                'host' => null,
                'values' => [],
                'message' => 'Missing selector or domain for DNS lookup.',
            ];
        }

        if (!function_exists('dns_get_record')) {
            return [
                'ok' => false,
                'host' => $host,
                'values' => [],
                'message' => 'dns_get_record is not available on this PHP runtime.',
            ];
        }

        $records = @dns_get_record($host, DNS_TXT);
        if (!is_array($records) || $records === []) {
            return [
                'ok' => false,
                'host' => $host,
                'values' => [],
                'message' => 'No TXT record found for this DKIM selector.',
            ];
        }

        $values = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            if (isset($record['txt']) && is_string($record['txt'])) {
                $values[] = trim($record['txt']);
                continue;
            }

            if (isset($record['entries']) && is_array($record['entries'])) {
                $joined = '';
                foreach ($record['entries'] as $entry) {
                    if (is_string($entry)) {
                        $joined .= $entry;
                    }
                }

                if ($joined !== '') {
                    $values[] = trim($joined);
                }
            }
        }

        if ($values === []) {
            return [
                'ok' => false,
                'host' => $host,
                'values' => [],
                'message' => 'TXT records were found but could not be parsed.',
            ];
        }

        return [
            'ok' => true,
            'host' => $host,
            'values' => $values,
            'message' => 'DKIM TXT record found.',
        ];
    }

    /**
     * Extracts p= public key from a DKIM TXT payload.
     *
     * @param string $txt DKIM TXT record value.
     *
     * @return string
     */
    public function extractDnsPublicKey(string $txt): string
    {
        if (!preg_match('/(?:^|;)\s*p\s*=\s*([^;]+)/i', $txt, $matches)) {
            return '';
        }

        return preg_replace('/\s+/', '', trim($matches[1])) ?? '';
    }

    /**
     * Extracts a base64 public key payload from a PEM string.
     *
     * @param string $pem Public key PEM.
     *
     * @return string
     */
    public function normalizePemPublicKey(string $pem): string
    {
        $cleaned = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----/i', '', $pem);
        if (!is_string($cleaned)) {
            return '';
        }

        return preg_replace('/\s+/', '', trim($cleaned)) ?? '';
    }

    /**
     * Builds public key PEM from configured private key.
     *
     * @return string
     */
    public function publicKeyPemFromPrivateKey(): string
    {
        $privateKeyPath = $this->resolvePrivateKeyPath();
        if ($privateKeyPath === '' || !is_file($privateKeyPath) || !is_readable($privateKeyPath)) {
            return '';
        }

        $privateKey = @file_get_contents($privateKeyPath);
        if (!is_string($privateKey) || trim($privateKey) === '') {
            return '';
        }

        $passphrase = (string) ($this->config['passphrase'] ?? '');
        $resource = @openssl_pkey_get_private($privateKey, $passphrase);
        if ($resource === false) {
            return '';
        }

        $details = @openssl_pkey_get_details($resource);
        if (!is_array($details) || !isset($details['key']) || !is_string($details['key'])) {
            return '';
        }

        return $details['key'];
    }

    /**
     * Builds the expected public key from configured private key.
     *
     * @return string
     */
    public function expectedPublicKeyFromPrivateKey(): string
    {
        return $this->normalizePemPublicKey($this->publicKeyPemFromPrivateKey());
    }

    /**
     * Builds the DKIM TXT value to publish in DNS.
     *
     * @return string
     */
    public function buildDnsRecordValue(): string
    {
        $publicKey = $this->expectedPublicKeyFromPrivateKey();
        if ($publicKey === '') {
            return '';
        }

        return sprintf('v=DKIM1; k=rsa; p=%s', $publicKey);
    }
}
