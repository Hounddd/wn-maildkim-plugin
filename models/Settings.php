<?php

namespace Hounddd\MailDkim\Models;

use Hounddd\MailDkim\Classes\DkimDiagnostics;
use Hounddd\MailDkim\Classes\DkimMailSigner;
use Symfony\Component\Mime\Email;
use Winter\Storm\Database\Model;

/**
 * Settings model used to render Mail DKIM backend diagnostics.
 *
 * @category Hounddd
 * @package  Hounddd\MailDkim
 * @author   Damien Mathieu <damien@hounddd.fr>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/Hounddd/wn-maildkim-plugin
 */
class Settings extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    /**
     * @var array<int, string>
     */
    public $implement = [\System\Behaviors\SettingsModel::class];

    /**
     * @var string
     */
    public $settingsCode = 'hounddd_maildkim_settings';

    /**
     * @var string
     */
    public $settingsFields = 'fields.yaml';

    /**
     * @var array<string, string>
     */
    public $rules = [];

    /**
     * Builds backend diagnostics data shown in the settings partial.
     *
     * @return array<string, mixed>
     */
    public function getDiagnostics(): array
    {
        /** @var DkimDiagnostics $diagnostics */
        $diagnostics = app(DkimDiagnostics::class);
        /** @var DkimMailSigner $signer */
        $signer = app(DkimMailSigner::class);

        $issues = $diagnostics->configurationIssues();
        $domain = $diagnostics->resolveDomain();
        $selector = $diagnostics->resolveSelector();
        $host = $diagnostics->resolveDnsHost();
        $lookup = $diagnostics->lookupDnsRecord();
        $dnsPayload = $this->resolveDnsPayload($lookup['values'] ?? []);
        $expectedPublicKey = $diagnostics->expectedPublicKeyFromPrivateKey();
        $dnsPublicKey = $dnsPayload !== ''
            ? $diagnostics->extractDnsPublicKey($dnsPayload)
            : '';

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'configuration' => [
                'ok' => $issues === [],
                'issues' => $issues,
                'domain' => $domain,
                'selector' => $selector,
                'dns_host' => $host,
                'private_key_path' => $diagnostics->resolvePrivateKeyPath(),
            ],
            'signature' => $this->buildSignatureCheck($signer, $issues),
            'dns' => [
                'ok' => (bool) ($lookup['ok'] ?? false),
                'message' => (string) ($lookup['message'] ?? ''),
                'host' => $host,
                'records' => $lookup['values'] ?? [],
                'record_payload' => $dnsPayload,
                'dns_public_key' => $dnsPublicKey,
                'expected_public_key' => $expectedPublicKey,
                'public_key_matches' => $dnsPublicKey !== ''
                    && $expectedPublicKey !== ''
                    ? $dnsPublicKey === $expectedPublicKey
                    : null,
            ],
            'materials' => [
                'dns_record_value' => $diagnostics->buildDnsRecordValue(),
                'public_key_pem' => $diagnostics->publicKeyPemFromPrivateKey(),
            ],
        ];
    }

    /**
     * Builds the signature check result for the settings screen.
     *
     * @param DkimMailSigner      $signer Signer service.
     * @param array<int, string>  $issues Configuration issues.
     *
     * @return array<string, mixed>
     */
    protected function buildSignatureCheck(DkimMailSigner $signer, array $issues): array
    {
        if ($issues !== []) {
            return [
                'ok' => false,
                'message' => 'Signature check skipped because configuration is incomplete.',
                'header' => null,
            ];
        }

        $email = (new Email())
            ->from('noreply@example.test')
            ->to('recipient@example.test')
            ->subject('DKIM diagnostics')
            ->text('DKIM diagnostics sample body.');

        if (!$signer->signSymfonyEmail($email)) {
            return [
                'ok' => false,
                'message' => 'DKIM signature could not be applied to the sample message.',
                'header' => null,
            ];
        }

        $header = $email->getHeaders()->get('DKIM-Signature');

        return [
            'ok' => true,
            'message' => 'DKIM signature was applied to the sample message.',
            'header' => $header !== null && method_exists($header, 'toString')
                ? $header->toString()
                : null,
        ];
    }

    /**
     * Selects the DKIM TXT payload from DNS lookup values.
     *
     * @param array<int, string> $records DNS TXT records.
     *
     * @return string
     */
    protected function resolveDnsPayload(array $records): string
    {
        foreach ($records as $record) {
            if (stripos($record, 'v=DKIM1') !== false && stripos($record, 'p=') !== false) {
                return $record;
            }
        }

        return '';
    }
}
