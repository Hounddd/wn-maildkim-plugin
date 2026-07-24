<?php

namespace Hounddd\MailDkim\Console;

use Hounddd\MailDkim\Classes\DkimDiagnostics;
use Hounddd\MailDkim\Classes\DkimMailSigner;
use Illuminate\Console\Command;
use Symfony\Component\Mime\Email;

/**
 * Runs a full DKIM diagnostics summary.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
class DoctorDkim extends Command
{
    /**
     * @var string
     */
    protected $signature = 'maildkim:doctor
        {--domain= : Override DKIM domain (d=)}
        {--selector= : Override DKIM selector (s=)}
        {--to= : Optional recipient for a real send test}';

    /**
     * @var string
     */
    protected $description = 'Runs configuration, signature, and DNS diagnostics for DKIM.';

    /**
     * Runs DKIM doctor checks.
     *
     * @return int
     */
    public function handle(): int
    {
        /* @var DkimMailSigner $signer */
        $signer = $this->laravel->make(DkimMailSigner::class);
        /* @var DkimDiagnostics $diagnostics */
        $diagnostics = $this->laravel->make(DkimDiagnostics::class);

        $failed = false;

        $this->line('== DKIM configuration check ==');
        $issues = $signer->getConfigurationIssues();
        if ($issues === []) {
            $this->info('PASS: configuration is valid.');
        } else {
            $failed = true;
            $this->error('FAIL: configuration issues detected.');
            foreach ($issues as $issue) {
                $this->line('- ' . $issue);
            }
        }

        $this->line('');
        $this->line('== DKIM signing check ==');

        $sample = (new Email())
            ->from('noreply@example.test')
            ->to('recipient@example.test')
            ->subject('DKIM doctor check')
            ->text('DKIM doctor signing check body.');

        if ($signer->signSymfonyEmail($sample)) {
            $this->info('PASS: sample message was signed.');
        } else {
            $failed = true;
            $this->error('FAIL: sample message could not be signed.');
        }

        $this->line('');
        $this->line('== DKIM DNS check ==');

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
            $failed = true;
            $this->error('FAIL: ' . $lookup['message']);
            if ($lookup['host'] !== null) {
                $this->line('Host: ' . $lookup['host']);
            }
        } else {
            $this->info('PASS: ' . $lookup['message']);
            $this->line('Host: ' . (string) $lookup['host']);
        }

        $to = trim((string) ($this->option('to') ?? ''));
        if ($to !== '') {
            $this->line('');
            $this->line('== Optional send-test hint ==');
            $this->line('Run: php artisan maildkim:send-test ' . $to);
        }

        $this->line('');
        if ($failed) {
            $this->error('DKIM doctor completed with failures.');

            return 1;
        }

        $this->info('DKIM doctor completed successfully.');

        return 0;
    }
}
