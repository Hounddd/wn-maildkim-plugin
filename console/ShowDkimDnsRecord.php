<?php

namespace Hounddd\MailDkim\Console;

use Hounddd\MailDkim\Classes\DkimDiagnostics;
use Illuminate\Console\Command;

/**
 * Shows the DKIM DNS record to publish from the configured private key.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
class ShowDkimDnsRecord extends Command
{
    /**
     * @var string
     */
    protected $signature = 'maildkim:dns-record
        {--domain= : Override DKIM domain (d=)}
        {--selector= : Override DKIM selector (s=)}
        {--raw : Output only the DNS TXT value}
        {--pem : Output the full PEM public key instead of the DNS value}';

    /**
     * @var string
     */
    protected $description = 'Shows the DKIM public key and DNS TXT record derived from the configured private key.';

    /**
     * Runs the DKIM DNS record output command.
     *
     * @return int
     */
    public function handle(): int
    {
        /* @var DkimDiagnostics $diagnostics */
        $diagnostics = $this->laravel->make(DkimDiagnostics::class);

        $issues = $diagnostics->configurationIssues();
        $filteredIssues = array_values(
            array_filter(
                $issues,
                static function (string $issue): bool {
                    return stripos($issue, 'DKIM is disabled') === false;
                }
            )
        );

        if ($filteredIssues !== []) {
            $this->error('Cannot build DKIM DNS record due to configuration issues:');
            foreach ($filteredIssues as $issue) {
                $this->line('- ' . $issue);
            }

            return 1;
        }

        $domain = trim((string) ($this->option('domain') ?? ''));
        if ($domain === '') {
            $domain = (string) ($diagnostics->resolveDomain() ?? '');
        }

        $selector = trim((string) ($this->option('selector') ?? ''));
        if ($selector === '') {
            $selector = $diagnostics->resolveSelector();
        }

        if ($this->option('pem')) {
            $pem = $diagnostics->publicKeyPemFromPrivateKey();
            if ($pem === '') {
                $this->error('Unable to derive public key PEM from configured private key.');

                return 1;
            }

            $this->line($pem);

            return 0;
        }

        $value = $diagnostics->buildDnsRecordValue();
        if ($value === '') {
            $this->error('Unable to derive DNS TXT value from configured private key.');

            return 1;
        }

        if ($this->option('raw')) {
            $this->line($value);

            return 0;
        }

        $host = $diagnostics->resolveDnsHost($selector, $domain !== '' ? $domain : null);
        if ($host === null) {
            $this->error('Missing selector or domain to build DNS host name.');

            return 1;
        }

        $this->info('Publish the following DKIM TXT record:');
        $this->line('Host: ' . $host);
        $this->line('Type: TXT');
        $this->line('Value: ' . $value);

        return 0;
    }
}
