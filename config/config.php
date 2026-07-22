<?php return [

    /*
    |--------------------------------------------------------------------------
    | Enable DKIM Signing
    |--------------------------------------------------------------------------
    |
    | Enables DKIM signing for outgoing emails when all required configuration
    | values are present and valid.
    |
    */

    'enabled' => env('DKIM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Private Key Path
    |--------------------------------------------------------------------------
    |
    | Absolute or project-relative path to the private key PEM file used for
    | DKIM signing.
    |
    */

    'private_key_path' => env('DKIM_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Signing Domain
    |--------------------------------------------------------------------------
    |
    | Domain used in the DKIM d= tag. If null, the host part of app.url is
    | used as fallback.
    |
    */

    'domain' => env('DKIM_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Signing Selector
    |--------------------------------------------------------------------------
    |
    | Selector used in DNS under selector._domainkey.<domain>.
    |
    */

    'selector' => env('DKIM_SELECTOR', 'dkim'),

    /*
    |--------------------------------------------------------------------------
    | Private Key Passphrase
    |--------------------------------------------------------------------------
    |
    | Optional passphrase used to decrypt the private key.
    |
    */

    'passphrase' => env('DKIM_PASSPHRASE'),

    /*
    |--------------------------------------------------------------------------
    | Signing Algorithm
    |--------------------------------------------------------------------------
    |
    | Supported values:
    | - rsa-sha256
    | - ed25519-sha256
    |
    */

    'algorithm' => env('DKIM_ALGORITHM', 'rsa-sha256'),

    /*
    |--------------------------------------------------------------------------
    | Log Successful Signatures
    |--------------------------------------------------------------------------
    |
    | Logs a message when DKIM signing succeeds. Disabled by default to avoid
    | noisy logs in production.
    |
    */

    'log_success' => env('DKIM_LOG_SUCCESS', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL Fallback
    |--------------------------------------------------------------------------
    |
    | Used to resolve a domain fallback when DKIM_DOMAIN is not set.
    |
    */

    'app_url' => config('app.url'),

];
