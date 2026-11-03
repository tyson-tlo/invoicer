# Invoicer

A single-organization invoicing application built with Laravel and Livewire. It gives one admin account a fast way to manage customers, create and send invoices, generate PDFs, and track drafts, outstanding balances, overdue invoices, and recent payments.

## What it does

- **Authentication**: login-only access with registration disabled. The first and only admin account is created through a one-time setup page with no email verification. Includes Fortify-based two-factor authentication and passkey support.
- **Dashboard**: KPIs for outstanding, overdue, draft, and paid invoices, plus draft, sent-unpaid, and recently paid sections with 7/30/90-day views.
- **Customers**: create and edit customers in a reusable modal, search customers, paginate results, and see how many invoices belong to each customer.
- **Invoices**: create and fully edit invoices, manage line items, set status and dates, add a human-readable description, and track draft/sent/paid states.
- **Invoice numbering**: automatic customer-prefixed identifiers with a persistent 6-digit sequence, custom identifiers, per-customer prefixes, and smart continuation of previous custom identifiers.
- **PDF invoices**: view PDFs inline in the browser or download them, using Dompdf and the uploaded branding logo.
- **Invoice email**: send an invoice PDF to the customer through Gmail, Outlook, Twilio SendGrid, or the local log mailer.
- **Search and filters**: search invoices by identifier, description, customer, or customer email; filter by status, customer, and issue date range; paginate by 10, 25, or 50 rows.
- **Branding**: upload a logo for invoice PDFs and a browser icon for the tab/sidebar.
- **Settings**: configure tax defaults, tax registration number, invoice email templates, sender details, email providers, logo, browser icon, profile, appearance, and security.

## Tech stack

- Laravel 13
- Livewire 4
- Flux UI 2
- Tailwind CSS 4 and Vite
- Laravel Fortify, passkeys, and Socialite
- Dompdf for PDF generation
- SQLite by default, with MySQL/PostgreSQL support
- Pest, Larastan/PHPStan, and Pint

## Requirements

- PHP 8.3 or newer with the usual Laravel PHP extensions
- Composer 2
- Node.js 22 or newer and npm
- SQLite, MySQL, or PostgreSQL
- A mail provider for production invoice email (Gmail, Outlook, or Twilio SendGrid)

## Local setup

1. Clone the repository and install PHP dependencies:

   ```bash
   composer install
   ```

2. Create the environment file and application key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Configure `.env`. For SQLite, create the database file:

   ```bash
   touch database/database.sqlite
   ```

   Set `APP_URL` to the URL you will use locally, usually `http://localhost:8000`.

4. Run the migrations:

   ```bash
   php artisan migrate
   ```

5. Link public storage so uploaded logos and browser icons are served:

   ```bash
   php artisan storage:link
   ```

6. Install and build the frontend:

   ```bash
   npm install
   npm run build
   ```

7. Start the application:

   ```bash
   composer run dev
   ```

   This starts the Laravel server, Vite, the queue listener, and log tailing. Alternatively, run `php artisan serve` and `npm run dev` in separate terminals.

8. Open `http://localhost:8000`. On a fresh database, click **Create admin account** (or visit `/setup`) and create the first admin user. No email verification is required.

   The setup page is available only while the database has no users. After the first admin exists, registration and setup are disabled and you use the login page.

## Configuration

Application settings are managed in the UI under **Settings**:

- **Invoicing**: default GST/tax rate, tax registration number, and invoice email subject/message templates.
- **Branding**: logo used on invoice PDFs and browser icon used in the tab and app sidebar.
- **Email**: active email provider, OAuth app credentials, sender details, and test email.
- **Profile**, **Security**, and **Appearance**: account and UI preferences.

### Email providers

Invoice emails are sent through the provider selected in **Settings → Email**. Provider credentials and tokens are stored encrypted in the database, not in `.env`.

The local-only **Log** option writes outgoing emails to `storage/logs/laravel.log`. It is shown only when `APP_ENV=local`.

#### Gmail

1. Create an OAuth client in Google Cloud Console.
2. Add this authorized redirect URI:

   ```text
   https://your-domain.com/settings/email/callback/gmail
   ```

3. Configure the OAuth consent screen and add the sending account as a test user while the app is unverified.
4. Enter the Google client ID and client secret in **Settings → Email**, save them, then click **Connect Gmail**.

The app requests the `https://mail.google.com/` scope because it sends through Gmail SMTP. This is a restricted Google scope, and Google may require OAuth verification for long-lived production use. If the OAuth app remains in testing mode, refresh tokens may expire and require reconnection. Twilio SendGrid is often simpler for production email.

#### Outlook

1. Register an application in Microsoft Entra ID.
2. Add this redirect URI as a web redirect URI:

   ```text
   https://your-domain.com/settings/email/callback/outlook
   ```

3. Add delegated API permissions for Microsoft Graph `User.Read` and `offline_access`, plus Office 365 Exchange Online `SMTP.Send`.
4. Enter the Microsoft client ID, client secret, and tenant ID (`common` or a specific tenant) in **Settings → Email**, save them, then click **Connect Outlook**.

The mailbox must allow SMTP AUTH. Some Microsoft 365 tenants disable SMTP AUTH by default; if sending fails, check the mailbox and tenant policies.

#### Twilio SendGrid

1. Create a SendGrid API key with permission to send mail.
2. Verify the sender address or domain in SendGrid.
3. Enter the API key and from address in **Settings → Email**, save them, then click **Connect SendGrid**.

#### System email

Invoice email uses the connected provider above. Other system email, such as password resets, still uses Laravel's default mailer from `.env` (`MAIL_MAILER`). Set a real SMTP provider there if you need password reset emails in production.

### Invoice numbering

New invoices default to the customer's prefix plus a persistent 6-digit sequence, for example `ACME000001`. Company names generate a prefix from their first four alphanumeric characters; customers can also have a custom prefix. The sequence is stored separately from the invoice ID, so deleting invoices does not reuse numbers.

Previous custom identifiers are continued by incrementing the first non-year number group, preserving leading zeros. Alphabetic suffixes roll over, and per-customer prefixes remain stable when a company is renamed. The invoice form also has a reset-to-suggested identifier action.

## Testing and code quality

Run the full quality suite:

```bash
composer test
```

Run individual checks:

```bash
composer lint        # Pint formatting
composer types:check # PHPStan/Larastan
php artisan test     # Pest tests
```

Build frontend assets:

```bash
npm run build
```

## Deployment

### Server requirements

Use a standard PHP application server with:

- PHP 8.3+ and required extensions
- Composer
- Node.js 22+ for the build step
- MySQL or PostgreSQL recommended for production (SQLite works for small single-user installs)
- A web server such as Nginx pointing at the `public` directory
- HTTPS, especially for OAuth callbacks, passkeys, and secure cookies

### Deploy steps

1. Clone the repository and install production PHP dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. Build frontend assets:

   ```bash
   npm ci
   npm run build
   ```

3. Create and configure `.env`:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Set at least:

   ```dotenv
   APP_NAME=Invoicer
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   APP_KEY=base64:...
   ```

   Configure your production database, session, cache, and default system mailer. Keep `APP_KEY` stable after deployment: encrypted email provider credentials depend on it.

4. Run migrations:

   ```bash
   php artisan migrate --force
   ```

5. Create the admin account. Open the deployed app and visit `/setup` (or click **Create admin account** on the home page). The setup page is available only while the database has no users, so complete this immediately after deployment. No email verification is required.

6. Link public storage and set permissions:

   ```bash
   php artisan storage:link
   chown -R www-data:www-data storage bootstrap/cache
   ```

7. Cache configuration and routes:

   ```bash
   php artisan optimize
   ```

8. Configure the web server document root to the `public` directory. Example Nginx server block:

   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /var/www/invoicer/public;
       index index.php;
       charset utf-8;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include fastcgi_params;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```

9. Enable HTTPS. Set `SESSION_SECURE_COOKIE=true` if your app is served over HTTPS.

10. Sign in and configure **Settings → Email**, **Invoicing**, and **Branding**. Use the production callback URLs shown on the email settings page.

### Production notes

- Invoice emails send synchronously. No queue worker is required for the current email flow. The default `QUEUE_CONNECTION=database` is fine; run `php artisan queue:work` if you later queue jobs or mail.
- There are no scheduled tasks in the current application. If you add any, configure the scheduler:

  ```cron
  * * * * * cd /var/www/invoicer && php artisan schedule:run >> /dev/null 2>&1
  ```

- Back up the database and `storage/app/public` (logos and browser icons). Also back up the application `.env` or at minimum the `APP_KEY`, because encrypted provider credentials cannot be recovered if the key is lost.
- Keep `APP_DEBUG=false` in production.
- Complete `/setup` immediately after deployment. Anyone who reaches it before you create the admin will be able to claim the admin account.
- Use a strong admin password and enable two-factor authentication or passkeys in **Settings → Security**.
- OAuth redirect URIs must use the production HTTPS domain and match `APP_URL`.

## License

This project is open source and released under the MIT license. You can use, modify, distribute, and sell it freely, including commercially. See [LICENSE](LICENSE) for the full text.
