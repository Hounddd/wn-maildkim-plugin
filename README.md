# Mail DKIM for Winter CMS

![Winter Mail DKIM](https://github.com/hounddd/wn-maildkim-plugin/blob/main/.github/MailDkim-plugin.png?raw=true)

This plugin adds a DKIM signature to outgoing emails using Symfony Mime.

## Installation
*Assume you are in the root of your Winter CMS installation.*

### 1. Install plugin
#### Using composer
Just run this command
```bash
composer require hounddd/wn-maildkim-plugin
```

#### Clone
Clone this repo into your winter plugins folder.

```bash
cd plugins
mkdir hounddd && cd hounddd
git clone https://github.com/Hounddd/wn-maildkim-plugin maildkim
```

### 2. Generate your DKIM keys

#### 2.1 If your provider manages DKIM keys

Some domain or email providers generate DKIM keys automatically.

- Contact their support or documentation to obtain:
   - the private key
   - the DKIM selector they created for your domain
- If the private key cannot be retrieved, generate your own key pair locally (next section), then add a new DKIM selector manually in your DNS zone.

#### 2.2 Generate keys locally (recommended)

Use OpenSSL to generate an RSA key pair (2048 bits recommended):

```bash
# Create the local folder that will store the private and public DKIM keys.
mkdir -p storage/app/uploads/protected/dkim
# Generate the private RSA key used to sign outgoing emails.
openssl genrsa -out storage/app/uploads/protected/dkim/private.key 2048
# Derive the public key that must be published in your DNS TXT record.
openssl rsa -in storage/app/uploads/protected/dkim/private.key -pubout -out storage/app/uploads/protected/dkim/public.pem
```

PowerShell alternative (Windows):

```powershell
# Create the local folder that will store the private and public DKIM keys.
New-Item -ItemType Directory -Force -Path "storage/app/uploads/protected/dkim" | Out-Null
# Generate the private RSA key used to sign outgoing emails.
openssl genrsa -out storage/app/uploads/protected/dkim/private.key 2048
# Derive the public key that must be published in your DNS TXT record.
openssl rsa -in storage/app/uploads/protected/dkim/private.key -pubout -out storage/app/uploads/protected/dkim/public.pem
```

> ⚠ It is not recommended to generate DKIM keys on an online service. Generate the key pair locally, and if you use a web-based tool at all, limit it to inspecting or formatting the public key after the private key has already been created on your machine.

### 3. Publish your DKIM key

Extract the DNS-safe public key value (single line, without headers):

```bash
awk 'NR>1 && !/-----/ { printf "%s", $0 } END { print "" }' storage/app/uploads/protected/dkim/public.pem
```

PowerShell alternative (Windows):

```powershell
(Get-Content "storage/app/uploads/protected/dkim/public.pem" | Where-Object { $_ -notmatch "-----" }) -join ""
```

This output is the `p=` value used in the DKIM TXT record, without the `-----BEGIN PUBLIC KEY-----` and `-----END PUBLIC KEY-----` lines.

If you want to do it manually, copy only the base64 text inside the public key file and join it into a single line.

Create a TXT DNS record on your domain:

1. Choose a selector, for example `dkim`.
2. Use this host/name:

   ```text
   dkim._domainkey.your-domain.tld
   ```

3. Use this TXT value:

   ```text
   v=DKIM1; k=rsa; p=<public key without spaces or line breaks>
   ```

4. Wait for DNS propagation, then verify the record:

   ```bash
   nslookup -type=TXT dkim._domainkey.your-domain.tld
   ```

   PowerShell alternative (Windows):

   ```powershell
   Resolve-DnsName -Name dkim._domainkey.your-domain.tld -Type TXT
   ```

Reference sites for DKIM checks:

- [MXToolbox DKIM Lookup](https://mxtoolbox.com/DKIMLookup.aspx) (quick DNS TXT lookup)
- [dmarcian DKIM Inspector](https://dmarcian.com/dkim-inspector/) (DKIM record syntax and value checks)
- [Mailhardener DKIM Record Check](https://www.mailhardener.com/tools/dkim-record-check) (additional DNS validation)

For an end-to-end test of a signed message (not only DNS), use [mail-tester](https://www.mail-tester.com/).

Keep the private key in a non-public file path, for example:

`storage/app/uploads/protected/dkim/private.key`

### 4. Update your site configuration

Then configure the plugin values to match your DNS setup:

- `DKIM_PRIVATE_KEY=storage/app/uploads/protected/dkim/private.key`
- `DKIM_DOMAIN=your-domain.tld`
- `DKIM_SELECTOR=dkim`

`DKIM_SELECTOR` must exactly match the selector used in your DNS host (for example: `dkim._domainkey.your-domain.tld` means `DKIM_SELECTOR=dkim`).

## Configuration

Configuration is defined in config/config.php and can be provided through environment variables.

**Example .env:**

```
DKIM_ENABLED=true
DKIM_PRIVATE_KEY=storage/app/uploads/protected/dkim/private.key
DKIM_DOMAIN=example.com
DKIM_SELECTOR=dkim
DKIM_PASSPHRASE=
DKIM_ALGORITHM=rsa-sha256
DKIM_LOG_SUCCESS=false
```

Supported algorithms:

- rsa-sha256 (default)
- ed25519-sha256

If DKIM_DOMAIN is not set, the plugin falls back to the host from app.url.

`DKIM_LOG_SUCCESS` can be set to `true` temporarily for diagnostics to log successful DKIM signature application.

## Runtime behavior

- The plugin listens to `mailer.prepareSend` event and signs at real send time.
- This includes queued emails processed by workers.
- If configuration is incomplete or signing fails, emails are still sent unsigned.
- Failures are logged for visibility.

## Backend diagnostics page

The plugin also provides a Winter CMS settings page that displays:

- configuration issues
- sample signature result (without sending a real email)
- DKIM DNS lookup result
- generated DNS TXT value and derived public key

Open the backend settings page for Mail DKIM to review these diagnostics in one place.

## Available commands

All commands return exit code `0` on success and `1` on failure.

### Signature check (local, no send)

Verifies that the current configuration can sign a sample email without sending real mail:

`php artisan maildkim:signature-check`

### DNS configuration check

Checks DKIM DNS TXT record for configured selector/domain:

`php artisan maildkim:dns-check`

Optional overrides:

`php artisan maildkim:dns-check --selector=dkim --domain=example.com`

This command also compares the DNS `p=` key with the public key derived from your configured private key (when readable).

### DNS record generation

Builds the DKIM public key record to publish from the configured private key:

`php artisan maildkim:dns-record`

Useful variants:

`php artisan maildkim:dns-record --raw`

`php artisan maildkim:dns-record --pem`

`php artisan maildkim:dns-record --selector=dkim --domain=example.com`

Use `--raw` when you only need the TXT value, and `--pem` for debugging or manual inspection of the derived public key.

### Send test email (real transport)

Sends a real test email using the configured mail transport:

`php artisan maildkim:send-test recipient@example.com`

Optional sender and subject:

`php artisan maildkim:send-test recipient@example.com --from=noreply@example.com --subject="DKIM test"`

Use this command for end-to-end validation (transport + signing at send time).

### Global DKIM diagnostic

Runs configuration, signature, and DNS checks in one command:

`php artisan maildkim:doctor`

Optional hints:

`php artisan maildkim:doctor --domain=example.com --selector=dkim --to=recipient@example.com`

## Logging convention

- Service classes use PSR-3 logger injection (`Psr\Log\LoggerInterface`) instead of the `Log` facade.
- This keeps the signing service framework-agnostic, easier to unit test, and still fully compatible with Winter's logger container binding.

## License

This plugin is open source software licensed under MIT.


***
Make awesome sites with ❄ [WinterCMS](https://wintercms.com) !
