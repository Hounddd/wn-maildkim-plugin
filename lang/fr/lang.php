<?php

return [

    'plugin' => [
        'name' => 'Mail DKIM',
        'description' => 'Ajouter la prise en charge des e-mails sortants signés avec DKIM dans Winter CMS.',
        'author' => 'Hounddd'
    ],

    'settings' => [
        'label' => 'Diagnostics Mail DKIM',
        'description' => 'Consulter la configuration DKIM, la signature, le DNS et les valeurs DNS générées.',
    ],

    'models' => [
        'settings' => [
            'label' => 'Diagnostics Mail DKIM',
            'description' => 'Consulter la configuration DKIM, la signature, le DNS et les valeurs DNS générées.',
            'diagnostics' => [
                'general' => [
                    'message' => 'Message :',
                    'not_resolved' => 'non résolu',
                    'not_configured' => 'non configuré',
                    'not_available' => 'non disponible',
                ],
                'status' => [
                    'ok' => 'OK',
                    'check_required' => 'Vérification requise',
                ],
                'overview' => [
                    'title' => 'Diagnostics Mail DKIM',
                    'recalculated' => 'Ces vérifications sont recalculées à chaque ouverture de la page de paramètres.',
                    'generated_at' => 'Généré le :',
                    'recommended_cli' => 'Équivalents CLI recommandés :',
                ],
                'configuration' => [
                    'title' => 'Configuration',
                    'domain' => 'Domaine :',
                    'selector' => 'Sélecteur :',
                    'dns_host' => 'Hôte DNS :',
                    'private_key_path' => 'Chemin de clé privée :',
                    'no_issue' => 'Aucun problème de configuration détecté.',
                ],
                'signature' => [
                    'title' => 'Vérification de signature',
                    'header_label' => 'En-tête DKIM-Signature',
                ],
                'dns' => [
                    'title' => 'Vérification DNS',
                    'lookup_host' => 'Hôte interrogé :',
                    'txt_records' => 'Enregistrements TXT',
                    'public_key_match' => 'Correspondance clé publique :',
                    'public_key_match_yes' => 'Le DNS et la clé privée locale sont cohérents.',
                    'public_key_match_no' => 'La valeur DNS p= ne correspond pas à la clé privée configurée.',
                    'public_key_match_unknown' => 'comparaison indisponible.',
                ],
                'materials' => [
                    'title' => 'Éléments DNS',
                    'description' => 'Valeurs à copier dérivées de la clé privée configurée.',
                    'txt_value' => 'Valeur TXT a publier',
                    'txt_unavailable' => 'Impossible de générer la valeur TXT DNS depuis la clé privée configurée.',
                    'public_key_pem' => 'Clé publique dérivée (PEM)',
                ],
            ],
        ],
    ],

];
