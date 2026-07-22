<?php

namespace Hounddd\MailDkim\Console;

use Hounddd\MailDkim\Classes\DkimMailSigner;
use Illuminate\Console\Command;
use Symfony\Component\Mime\Email;

class VerifyDkimSignature extends Command
{
    /**
     * @var string
     */
    protected $name = 'maildkim:verify';

    /**
     * @var string
     */
    protected $description = 'Verifies that DKIM signing configuration can sign a sample email.';

    public function handle(): int
    {
        /** @var DkimMailSigner $signer */
        $signer = $this->laravel->make(DkimMailSigner::class);

        $issues = $signer->getConfigurationIssues();
        if (!empty($issues)) {
            $this->error('DKIM verification failed due to configuration issues:');
            foreach ($issues as $issue) {
                $this->line('- ' . $issue);
            }

            $this->line('Tip: update your .env values then clear config cache (`php artisan config:clear`).');

            return 1;
        }

        $email = (new Email())
            ->from('noreply@example.test')
            ->to('recipient@example.test')
            ->subject('DKIM verification')
            ->text('DKIM verification email body.');

        if (!$signer->signSymfonyEmail($email)) {
            $this->error('DKIM signature was not applied.');
            $this->line('Check runtime signing errors in: ' . $this->describeLogTargets());

            return 1;
        }

        $header = $email->getHeaders()->get('DKIM-Signature');

        $this->info('DKIM signature was successfully applied to a sample email.');
        if ($header !== null && method_exists($header, 'toString')) {
            $this->line($header->toString());
        }

        return 0;
    }

    protected function describeLogTargets(): string
    {
        $defaultChannel = (string) config('logging.default', 'single');
        $paths = [];

        $defaultPath = config('logging.channels.' . $defaultChannel . '.path');
        if (is_string($defaultPath) && trim($defaultPath) !== '') {
            $paths[] = $defaultPath;
        }

        if ($defaultChannel === 'stack') {
            $channels = (array) config('logging.channels.stack.channels', []);
            foreach ($channels as $channel) {
                $path = config('logging.channels.' . $channel . '.path');
                if (is_string($path) && trim($path) !== '') {
                    $paths[] = $path;
                }
            }
        }

        $paths = array_values(array_unique(array_filter($paths)));
        if (empty($paths)) {
            return 'default logger channel `' . $defaultChannel . '` (no explicit file path configured)';
        }

        return implode(', ', $paths);
    }
}
