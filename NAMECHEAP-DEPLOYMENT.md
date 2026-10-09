# Deploy Woodhask on Namecheap

This guide is for a Namecheap shared-hosting account with cPanel. The contact form is a PHP application: it stores enquiries in MySQL and sends them to `woodhask.ediomu@woodhask.com` over authenticated SMTP.

> **Important prerequisite:** `contact.php` reads credentials from PHP environment variables using `getenv()`. Before uploading real credentials, ask Namecheap how your specific hosting plan passes environment variables to PHP. Do not assume that a cPanel PHP setting or an Apache `SetEnv` directive is available to PHP-FPM. The deployment is not ready until PHP can read all the required values without placing secrets in a publicly accessible file.

## 1. Open cPanel and check your hosting plan

1. Sign in to your [Namecheap account](https://www.namecheap.com/myaccount/login.aspx).
2. Open **Hosting List**, find the hosting subscription for the domain, and select **Go to cPanel**. Namecheap's [cPanel access guide](https://www.namecheap.com/support/knowledgebase/article.aspx/10132/27/where-can-i-log-into-my-cpanel/) describes this route.
3. Confirm that the plan supports PHP, MySQL/MariaDB, HTTPS, and the PHP environment-variable mechanism required above. If any of these are missing, contact Namecheap before proceeding.

## 2. Point the domain to the hosting account

If the domain is registered with Namecheap, use the nameservers or DNS records specified in the hosting account's welcome email and cPanel/hosting details. Make DNS changes wherever the domain's active nameservers are managed; Namecheap's **Advanced DNS** page will not control a domain using another provider's nameservers.

Do not replace existing MX, SPF, DKIM, or other email DNS records unless Namecheap or your email provider instructs you to. Allow time for DNS changes to propagate, then enable HTTPS for the domain in cPanel and verify the site loads over `https://`.

## 3. Select PHP and verify required extensions

In cPanel, open **MultiPHP Manager** or the PHP version selector available on your account. Set the domain to PHP **8.1 or later**. Make sure these extensions are enabled for the website's PHP version:

- `pdo_mysql`
- `mbstring`
- `openssl`
- `session`

The CLI PHP version shown in Terminal may differ from the web server's PHP version. Ask Namecheap support to confirm the version and extensions used by the domain if they are not clear in cPanel.

## 4. Create the database and import the schema

1. In cPanel, open **MySQL Databases** (or **Database Wizard**) and create a database.
2. Create a dedicated database user with a strong, unique password. Add the user to the database and grant only `SELECT`, `INSERT`, and `UPDATE`.
3. Record the **full** database name and username shown in cPanel. Shared hosting often prefixes them with the cPanel account name.
4. Open **phpMyAdmin**, select the new database, choose **Import**, and import this project's [`schema.sql`](./schema.sql).
5. Use the database host supplied by Namecheap. On many cPanel accounts it is `localhost`, but confirm this in the hosting details if unsure.

The application does not create tables automatically. Import the schema with phpMyAdmin or another approved database administration tool; the runtime database user does not need permission to create tables.

## 5. Upload the project

Use cPanel **File Manager** or SFTP to upload the site files to the document root assigned to the domain (commonly `public_html` for the primary domain, but add-on domains may have a different document root).

Upload the website pages and assets, plus these backend files:

- `contact.php`
- `.htaccess`
- `composer.json`
- `composer.lock`
- `schema.sql`

Keep `schema.sql`, Composer files, and the `vendor` directory protected by the included Apache rules. Do not remove or replace the project's `.htaccess` security and legacy-route rules. If the domain is not served by Apache or `.htaccess` is disabled, ask Namecheap support for equivalent server rules before launch.

### Install PHPMailer

If Namecheap Terminal and Composer are available:

1. Enable SSH from cPanel's **Manage Shell** or **SSH Access** interface. Namecheap's [SSH guide](https://www.namecheap.com/support/knowledgebase/article.aspx/10040/2210/how-to-enable-ssh-shell-in-cpanel/) explains that enabling SSH makes Terminal available in cPanel.
2. Open cPanel **Terminal**, change to the website's document root, and install the locked production dependencies:

   ```sh
   cd ~/public_html
   composer install --no-dev --optimize-autoloader
   ```

   Replace `~/public_html` with the actual document root if this domain uses another directory.

If Composer is unavailable on the hosting account, run that command on a compatible local PHP/Composer environment from the project root, then upload the resulting `vendor/` directory via SFTP. Ensure `vendor/autoload.php` exists on the host. The repository's `.gitignore` excludes `vendor/`, so a normal Git checkout or source upload may not include it.

## 6. Configure secrets for PHP

The form will not process a real submission until PHP can read all of these values:

```text
DB_HOST=the database host supplied by Namecheap
DB_PORT=3306
DB_NAME=the full cPanel database name
DB_USER=the full cPanel database username
DB_PASSWORD=the database user's password
APP_KEY=a private random value of at least 32 characters
SMTP_HOST=the SMTP server supplied by your email provider
SMTP_PORT=the SMTP port supplied by your email provider
SMTP_USERNAME=the authenticated SMTP mailbox or username
SMTP_PASSWORD=the SMTP password
SMTP_ENCRYPTION=tls
SMTP_FROM=an address authorized to send through that SMTP account
SMTP_FROM_NAME=Woodhask Certified Public Accountants
```

The recipient is fixed in `contact.php` as `woodhask.ediomu@woodhask.com`; it is not an environment variable. For `APP_KEY`, generate a random value using PHP:

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Enter these settings only through a server-side facility that Namecheap confirms is available and safe for your hosting plan. Do not put real secrets in browser JavaScript, a public HTML file, Git, or a publicly downloadable configuration file. Do not paste them into a support ticket or chat.

For a Namecheap-hosted mailbox, get the SMTP hostname, port, encryption, and username from cPanel **Email Accounts → Connect Devices / Set Up Mail Client**. For Namecheap Private Email or another mail provider, use that provider's SMTP settings instead. Use the authenticated mailbox as `SMTP_FROM` unless the provider explicitly authorizes a different sender. The visitor's email address is used only as the reply-to address.

If cPanel does not provide a suitable secure environment-variable mechanism, ask Namecheap support whether their PHP handler passes Apache `SetEnv` values through to PHP-FPM. The current application requires the values to be available to PHP's `getenv()`; do not add secrets to `.htaccess` unless the provider confirms that method is supported and you can keep that file server-protected and out of source control. If the account cannot securely supply the variables, pause deployment and request a supported configuration approach before changing the application.

## 7. Verify the deployment

1. Visit `https://your-domain/contact.php`. Confirm the page loads with no PHP errors and the form is displayed.
2. Submit a genuine test enquiry that the team expects to receive.
3. In phpMyAdmin, check that a row was added to `contact_submissions` and has `email_status = 'sent'`.
4. Confirm the message arrives at `woodhask.ediomu@woodhask.com`. Check spam/quarantine if it is not in the inbox.
5. If the form reports an error, use the hosting PHP error log and the displayed message to diagnose it. A database save followed by an SMTP failure is recorded as `failed`; do not treat the form as successfully delivered.
6. Configure and verify SPF and DKIM for the authenticated sender domain with the email provider.

Never expose PHP error details or diagnostic scripts publicly. Remove any temporary diagnostic file immediately after use. Back up the database securely and restrict access to cPanel, database credentials, SMTP credentials, and server logs.

## Namecheap support checklist

If any requirement is unclear, ask Namecheap support:

- Which PHP version and PHP handler (including PHP-FPM, if used) serve this domain?
- Are `pdo_mysql`, `mbstring`, `openssl`, and `session` enabled for that PHP version?
- What is the supported, secure way to provide environment variables that PHP can read with `getenv()`? Are Apache `SetEnv` values passed through to the PHP handler?
- Is outbound authenticated SMTP available from this hosting plan, and what host/port/encryption settings should be used?
- What database hostname should the application use?

Do not include actual passwords, secret keys, or customer enquiry content in a support request.
