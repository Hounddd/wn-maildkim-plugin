<?php 

namespace Hounddd\MailDkim;

use Config;
use Event;
use Hounddd\MailDkim\Classes\DkimMailSigner;
use Hounddd\MailDkim\Console\VerifyDkimSignature;
use Psr\Log\LoggerInterface;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'hounddd.maildkim::lang.plugin.name',
            'description' => 'hounddd.maildkim::lang.plugin.description',
            'author'      => 'Hounddd',
            'icon'        => 'icon-envelope',
            'homepage'    => 'https://github.com/Hounddd/wn-maildkim-plugin',
        ];
    }

    public function register(): void
    {
        $this->app->singleton(DkimMailSigner::class, function ($app) {
            $logger = $app->make(LoggerInterface::class);

            return new DkimMailSigner(
                (array) Config::get('hounddd.maildkim::config', []),
                $logger
            );
        });

        $this->registerConsoleCommand('maildkim.verify', VerifyDkimSignature::class);
    }

    public function boot(): void
    {
        Event::listen('mailer.prepareSend', function ($mailer, $view, $message): void {
            if (!is_object($message) || !method_exists($message, 'getSymfonyMessage')) {
                return;
            }

            $symfonyMessage = $message->getSymfonyMessage();
            if (!is_object($symfonyMessage)) {
                return;
            }

            $this->app->make(DkimMailSigner::class)->signSymfonyEmail($symfonyMessage);
        });
    }
}
