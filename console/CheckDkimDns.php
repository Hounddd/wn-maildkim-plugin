<?php

namespace Hounddd\MailDkim\Console;

use Hounddd\MailDkim\Classes\DkimDiagnostics;
use Illuminate\Console\Command;

/**
 * Validates DKIM DNS records for the configured selector/domain.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
class CheckDkimDns extends Command
{
    /**
     * @var string
     */
    protected $signature = 'maildkim:dns-check
        {--domain= : Override DKIM domain (d=)}
        {--selector= : Override DKIM selector (s=)}';

    /**
     * @var string
     */
    protected $description = 'Checks DKIM TXT DNS record validity and key consistency.';

    /**
     * Runs DKIM DNS diagnostics.
     *
     * @return int
     */
    public function handle(): int
    {
        $diagnostics = $this->laravel->make(DkimDiagnostics::class);

        $domain = trim((string) ($this->option('domain') ?? ''));
        if ($domain === '') {
            $domain = (string) ($diagnostics->resolveDomain() ?? '');
        }

        $selector = trim((string) ($this->option('selector') ?? ''));
        if ($selector === '') {
            $selector = $diagnostics->resolveSelector();
        }

        $lookup = $diagnostics->lookupDnsRecord($selector, $domain !== '' ? $domain : null);
        if (!$lookup['ok']) {
            $this->error('DNS check failed: ' . $lookup['message']);

            if ($lookup['host'] !== null) {
                $this->line('Host: ' . $lookup['host']);
            }

            return 1;
        }

        $host = (string) $lookup['host'];
        $this->info('DNS TXT record found for: ' . $host);

        $candidate = '';
        foreach ($lookup['values'] as $index => $value) {
            $this->line(sprintf('TXT[%d]: %s', $index + 1, $value));

            if (stripos($value, 'v=DKIM1') !== false && stripos($value, 'p=') !== false) {
                $candidate = $value;
            }
        }

        if ($candidate === '') {
            $this->error('No DKIM payload found in TXT records (expected v=DKIM1 and p=).');

            return 1;
        }

        $dnsPublicKey = $diagnostics->extractDnsPublicKey($candidate);
        if ($dnsPublicKey === '') {
            $this->error('Unable to extract p= public key from DNS DKIM record.');

            return 1;
        }

        $expectedPublicKey = $diagnostics->expectedPublicKeyFromPrivateKey();
        if ($expectedPublicKey === '') {
            $this->warn('Could not derive expected public key from configured private key.');
            $this->line('Skipping DNS key consistency comparison.');

            return 0;
        }

        if ($dnsPublicKey !== $expectedPublicKey) {
            $this->error('DNS public key does not match configured private key.');
            $this->line('Tip: regenerate DNS p= value from your private key and wait for propagation.');

            return 1;
        }

        $this->info('DNS public key matches configured private key.');

        return 0;
    }
}
