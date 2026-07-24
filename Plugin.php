<?php

namespace Hounddd\MailDkim;

use Hounddd\MailDkim\Classes\DkimDiagnostics;
use Hounddd\MailDkim\Classes\DkimMailSigner;
use Hounddd\MailDkim\Console\CheckDkimDns;
use Hounddd\MailDkim\Console\CheckDkimSignature;
use Hounddd\MailDkim\Console\DoctorDkim;
use Hounddd\MailDkim\Console\SendDkimTestEmail;
use Hounddd\MailDkim\Console\ShowDkimDnsRecord;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Psr\Log\LoggerInterface;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

/**
 * Registers the MailDkim plugin services and listeners.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
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

    /**
     * Registers the settings page used for DKIM diagnostics.
     *
     * @return array<string, array<string, mixed>>
     */
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'hounddd.maildkim::lang.models.settings.label',
                'description' => 'hounddd.maildkim::lang.models.settings.description',
                'category' => SettingsManager::CATEGORY_MAIL,
                'icon' => 'icon-envelope-o',
                'class' => 'Hounddd\MailDkim\Models\Settings',
                'order' => 5000,
                'keywords' => 'mail dkim dns diagnostics email',
            ],
        ];
    }

    /**
     * Registers plugin services and console commands.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            DkimMailSigner::class,
            function ($app) {
                $logger = $app->make(LoggerInterface::class);

                return new DkimMailSigner(
                    (array) Config::get('hounddd.maildkim::config', []),
                    $logger
                );
            }
        );

        $this->app->singleton(
            DkimDiagnostics::class,
            function ($app) {
                return new DkimDiagnostics(
                    (array) Config::get('hounddd.maildkim::config', []),
                    $app->make(DkimMailSigner::class)
                );
            }
        );

        $this->registerConsoleCommand('maildkim.signature_check', CheckDkimSignature::class);
        $this->registerConsoleCommand('maildkim.dns_check', CheckDkimDns::class);
        $this->registerConsoleCommand('maildkim.dns_record', ShowDkimDnsRecord::class);
        $this->registerConsoleCommand('maildkim.send_test', SendDkimTestEmail::class);
        $this->registerConsoleCommand('maildkim.doctor', DoctorDkim::class);
    }

    /**
     * Attaches the send-time mail signing listener.
     *
     * @return void
     */
    public function boot(): void
    {
        Event::listen(
            'mailer.prepareSend',
            function ($mailer, $view, $message): void {
                if (
                    !is_object($message)
                    || !method_exists($message, 'getSymfonyMessage')
                ) {
                    return;
                }

                $symfonyMessage = $message->getSymfonyMessage();
                if (!is_object($symfonyMessage)) {
                    return;
                }

                $this->app
                    ->make(DkimMailSigner::class)
                    ->signSymfonyEmail($symfonyMessage);
            }
        );
    }
}
