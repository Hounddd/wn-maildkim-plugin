<?php

namespace Hounddd\MailDkim\Classes;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;

/**
 * Signs outgoing emails with DKIM when configuration is complete and valid.
 */
class DkimMailSigner
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected LoggerInterface $logger;

    protected ?DkimSigner $signer = null;

    protected bool $signerInitialized = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Signs the given Symfony email in place.
     */
    public function signSymfonyEmail(object $email): bool
    {
        if (!$email instanceof Email) {
            return false;
        }

        if (!$this->isEnabled()) {
            return false;
        }

        $signer = $this->getSigner();
        if ($signer === null) {
            return false;
        }

        try {
            $signedEmail = $signer->sign($email);
            $email->setHeaders($signedEmail->getHeaders());

            $signed = $email->getHeaders()->has('DKIM-Signature');
            if ($signed && $this->shouldLogSuccess()) {
                $this->logger->info('DKIM signature applied.', [
                    'selector' => $this->selector(),
                    'domain' => $this->resolveDomain(),
                    'subject' => $email->getSubject(),
                ]);
            }

            return $signed;
        } catch (\Throwable $exception) {
            $this->logger->error('DKIM signing failed. Email will be sent unsigned.', [
                'exception' => $exception->getMessage(),
                'selector' => $this->selector(),
                'domain' => $this->resolveDomain(),
            ]);

            return false;
        }
    }

    /**
     * Returns a list of configuration issues preventing DKIM signing.
     *
     * @return array<int, string>
     */
    public function getConfigurationIssues(): array
    {
        $issues = [];

        if (!$this->isEnabled()) {
            $issues[] = 'DKIM is disabled (`DKIM_ENABLED=false`).';
        }

        $keyPath = trim((string) ($this->config['private_key_path'] ?? ''));
        if ($keyPath === '') {
            $issues[] = 'Missing private key path (`DKIM_PRIVATE_KEY`).';
        } elseif (!is_file($keyPath) || !is_readable($keyPath)) {
            $issues[] = sprintf('Private key file is missing or unreadable: %s', $keyPath);
        }

        if ($this->resolveDomain() === null) {
            $issues[] = 'Missing domain (`DKIM_DOMAIN`) and app.url fallback is not usable.';
        }

        if ($this->selector() === '') {
            $issues[] = 'Selector is empty (`DKIM_SELECTOR`).';
        }

        $algorithm = strtolower(trim((string) ($this->config['algorithm'] ?? 'rsa-sha256')));
        $allowed = ['rsa-sha256', 'ed25519-sha256'];
        if (!in_array($algorithm, $allowed, true)) {
            $issues[] = sprintf(
                'Unsupported algorithm `%s` (allowed: rsa-sha256, ed25519-sha256).',
                $algorithm
            );
        }

        return $issues;
    }

    /**
     * Builds and caches the signer instance.
     */
    protected function getSigner(): ?DkimSigner
    {
        if ($this->signerInitialized) {
            return $this->signer;
        }

        $this->signerInitialized = true;

        $keyPath = trim((string) ($this->config['private_key_path'] ?? ''));
        if ($keyPath === '') {
            $this->logger->warning('DKIM signing is enabled but private_key_path is missing.');

            return null;
        }

        if (!is_file($keyPath) || !is_readable($keyPath)) {
            $this->logger->warning('DKIM private key file is missing or unreadable.', [
                'private_key_path' => $keyPath,
            ]);

            return null;
        }

        $domain = $this->resolveDomain();
        if ($domain === null) {
            $this->logger->warning('DKIM signing is enabled but no valid domain could be resolved.');

            return null;
        }

        $selector = $this->selector();
        if ($selector === '') {
            $this->logger->warning('DKIM signing is enabled but selector is empty.');

            return null;
        }

        $keyContent = file_get_contents($keyPath);
        if ($keyContent === false || trim($keyContent) === '') {
            $this->logger->warning('DKIM private key file is empty or unreadable.', [
                'private_key_path' => $keyPath,
            ]);

            return null;
        }

        $passphrase = (string) ($this->config['passphrase'] ?? '');

        try {
            $this->signer = new DkimSigner(
                $keyContent,
                $domain,
                $selector,
                $this->resolveSignerOptions(),
                $passphrase
            );
        } catch (\Throwable $exception) {
            $this->logger->error('DKIM signer could not be created. Emails will be sent unsigned.', [
                'exception' => $exception->getMessage(),
                'selector' => $selector,
                'domain' => $domain,
            ]);

            return null;
        }

        return $this->signer;
    }

    protected function isEnabled(): bool
    {
        $enabled = $this->config['enabled'] ?? false;

        if (is_bool($enabled)) {
            return $enabled;
        }

        return filter_var($enabled, FILTER_VALIDATE_BOOL) === true;
    }

    protected function resolveDomain(): ?string
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

    protected function selector(): string
    {
        return trim((string) ($this->config['selector'] ?? 'dkim'));
    }

    protected function shouldLogSuccess(): bool
    {
        $value = $this->config['log_success'] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL) === true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSignerOptions(): array
    {
        $algorithm = strtolower(trim((string) ($this->config['algorithm'] ?? 'rsa-sha256')));
        $allowed = ['rsa-sha256', 'ed25519-sha256'];

        if (!in_array($algorithm, $allowed, true)) {
            $this->logger->warning('Unsupported DKIM algorithm configured, falling back to rsa-sha256.', [
                'algorithm' => $algorithm,
            ]);

            $algorithm = 'rsa-sha256';
        }

        return [
            'algorithm' => $algorithm,
        ];
    }
}
