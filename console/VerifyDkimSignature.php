<?php

namespace Hounddd\MailDkim\Console;

use Hounddd\MailDkim\Classes\DkimMailSigner;
use Illuminate\Console\Command;
use Symfony\Component\Mime\Email;

/**
 * Verifies that the configured DKIM signer can sign a sample message.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
class VerifyDkimSignature extends Command
{
    /**
     * Command name.
     *
     * @var string
     */
    protected $name = 'maildkim:verify';

    /**
     * Command description.
     *
     * @var string
     */
    protected $description = 'Verifies DKIM signing on a sample email.';

    /**
     * Runs the DKIM verification command.
     *
     * @return int
     */
    public function handle(): int
    {
        /* @var DkimMailSigner $signer */
        $signer = $this->laravel->make(DkimMailSigner::class);

        $issues = $signer->getConfigurationIssues();
        if (!empty($issues)) {
            $this->error('DKIM verification failed due to configuration issues:');
            foreach ($issues as $issue) {
                $this->line('- ' . $issue);
            }

            $this->line(
                'Tip: update your .env values then clear config cache '
                . '(`php artisan config:clear`).'
            );

            return 1;
        }

        $email = (new Email())
            ->from('noreply@example.test')
            ->to('recipient@example.test')
            ->subject('DKIM verification')
            ->text('DKIM verification email body.');

        if (!$signer->signSymfonyEmail($email)) {
            $this->error('DKIM signature was not applied.');
            $this->line('Check your Winter site logs for runtime signing errors.');

            return 1;
        }

        $header = $email->getHeaders()->get('DKIM-Signature');

        $this->info('DKIM signature was successfully applied to a sample email.');
        if ($header !== null && method_exists($header, 'toString')) {
            $this->line($header->toString());
        }

        return 0;
    }
}
