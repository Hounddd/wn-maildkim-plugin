<?php

return [

    'plugin' => [
        'name' => 'Mail DKIM',
        'description' => 'Add support for DKIM-signed outgoing emails in Winter CMS.',
        'author' => 'Hounddd'
    ],

    'settings' => [
        'label' => 'Mail DKIM diagnostics',
        'description' => 'Review DKIM configuration, signature, DNS, and generated DNS values.',
    ],

    'models' => [
        'settings' => [
            'label' => 'Mail DKIM diagnostics',
            'description' => 'Review DKIM configuration, signature, DNS, and generated DNS values.',
            'diagnostics' => [
                'general' => [
                    'message' => 'Message:',
                    'not_resolved' => 'not resolved',
                    'not_configured' => 'not configured',
                    'not_available' => 'not available',
                ],
                'status' => [
                    'ok' => 'OK',
                    'check_required' => 'Check required',
                ],
                'overview' => [
                    'title' => 'Mail DKIM diagnostics',
                    'recalculated' => 'These checks are recalculated every time the settings page is opened.',
                    'generated_at' => 'Generated at:',
                    'recommended_cli' => 'Recommended CLI equivalents:',
                ],
                'configuration' => [
                    'title' => 'Configuration',
                    'domain' => 'Domain:',
                    'selector' => 'Selector:',
                    'dns_host' => 'DNS host:',
                    'private_key_path' => 'Private key path:',
                    'no_issue' => 'No configuration issue detected.',
                ],
                'signature' => [
                    'title' => 'Signature check',
                    'header_label' => 'DKIM-Signature header',
                ],
                'dns' => [
                    'title' => 'DNS check',
                    'lookup_host' => 'Lookup host:',
                    'txt_records' => 'TXT records',
                    'public_key_match' => 'Public key match:',
                    'public_key_match_yes' => 'DNS and local private key are consistent.',
                    'public_key_match_no' => 'DNS p= value does not match the configured private key.',
                    'public_key_match_unknown' => 'comparison unavailable.',
                ],
                'materials' => [
                    'title' => 'DNS material',
                    'description' => 'Copy-ready values derived from the configured private key.',
                    'txt_value' => 'TXT value to publish',
                    'txt_unavailable' => 'DNS TXT value could not be generated from the configured private key.',
                    'public_key_pem' => 'Derived public key (PEM)',
                ],
            ],
        ],
    ],

];
