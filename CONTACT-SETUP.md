# Contact form deployment

The contact form uses PHP, MySQL, and authenticated SMTP. A submission is stored in MySQL and then sent to `woodhask.ediomu@woodhask.com`. Configure and test these steps on the production host before publishing the PHP contact page.

## Requirements

- PHP 8.1 or later with `pdo_mysql`, `openssl`, `session`, and `mbstring`
- MySQL 8.0 or MariaDB 10.5 or later
- Composer
- SMTP credentials from the hosting provider
- Apache with `.htaccess` enabled, or equivalent server rules

## Configure the database

1. Create a MySQL database and a dedicated runtime user. Grant that user only `SELECT`, `INSERT`, and `UPDATE` on the database.
2. Import `schema.sql` using the hosting database administrator or a temporary schema-install user with create-table permission. The website does not create tables automatically.
3. Install dependencies in the site directory:

   ```sh
   composer install --no-dev --optimize-autoloader
   ```

## Configure secrets on the host

Set these environment variables in the hosting control panel or PHP-FPM pool. On Apache shared hosting, the provider may support `SetEnv` in a server-protected `.htaccess`; confirm that PHP receives the values before using that method. Never put real credentials in a public file, source control, or browser JavaScript.

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_database_password
APP_KEY=generate_a_random_secret_of_at_least_32_characters
SMTP_HOST=your_host_smtp_server
SMTP_PORT=587
SMTP_USERNAME=your_smtp_username
SMTP_PASSWORD=your_smtp_password
SMTP_ENCRYPTION=tls
SMTP_FROM=the_authenticated_sender_address
SMTP_FROM_NAME=Woodhask Certified Public Accountants
```

Generate `APP_KEY` with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.

Use the host's SMTP hostname, port, encryption mode, and authenticated sender address. Port `587` with `tls` is a common configuration, but the values supplied by the host take precedence. The recipient is fixed in `contact.php`.

## Publish and test

1. Upload the site, including `contact.php`, `.htaccess`, `schema.sql`, `composer.json`, and `composer.lock`. Run `composer install --no-dev --optimize-autoloader` on the host (or upload the generated `vendor` directory via SFTP if Composer is unavailable); the included Apache rules block web requests to Composer files and `vendor`.
2. Confirm that PHP can read the host environment variables and connect to MySQL and SMTP.
3. Visit `contact.php` over HTTPS. Submit a real test enquiry and confirm both that a row appears in `contact_submissions` with `email_status = 'sent'` and that the email arrives at the Woodhask inbox.
4. Configure SPF and DKIM for the authenticated sender domain with the host to improve delivery.

The browser displays success only after SMTP accepts the message. If database save or mail delivery fails, the form reports the issue instead. A failed SMTP send remains recorded with `email_status = 'failed'`; server logs contain diagnostic details without the submitted message.

Keep database backups private and retention-limited. Restrict access to the MySQL database, SMTP credentials, and PHP/server logs to trusted administrators.
