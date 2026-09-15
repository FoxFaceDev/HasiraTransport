# cPanel deployment

Production URL: `https://hasira.natron-company.co`

## 1. Configure cPanel

1. In **MultiPHP Manager**, select PHP 8.2 or newer for the subdomain.
2. In **Domains**, set the document root for `hasira.natron-company.co` to:

   ```text
   /home/CPANEL_USERNAME/hasiratransport/public
   ```

3. Create the MySQL database and user, grant the user **All Privileges**, and import the existing database.

Only the `public` directory may be exposed as the document root. Do not place the complete Laravel project inside a publicly accessible directory.

## 2. Upload the application

Upload `hasiratransport-cpanel.zip` to `/home/CPANEL_USERNAME/hasiratransport` using File Manager, then extract it there. After extraction, this path must exist:

```text
/home/CPANEL_USERNAME/hasiratransport/artisan
```

The project must not be inside an additional nested `hasiratransport` directory.

## 3. Configure the environment

Open cPanel Terminal and run:

```bash
cd ~/hasiratransport
cp deploy/cpanel.env.example .env
nano .env
```

Replace `CPANEL_DATABASE_NAME`, `CPANEL_DATABASE_USER`, and `CPANEL_DATABASE_PASSWORD` with the exact values created in cPanel. cPanel commonly prefixes database and user names with the account username.

Never upload the local development `.env` file or expose the production `.env` publicly.

## 4. Install and activate

Verify that Terminal uses PHP 8.2 or newer, then run:

```bash
cd ~/hasiratransport
php -v
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public/reports bootstrap/cache
chmod -R 775 storage bootstrap/cache
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan storage:link
php artisan optimize
```

If `php -v` reports a version below 8.2, stop and select a newer CLI PHP binary before running Composer or Artisan.

The roles seeder is safe for the imported users and ensures that production permissions match the application. Do not run the general `DatabaseSeeder` on production because it creates a default administrator account.

## 5. Verify

Run:

```bash
php artisan about --only=environment
curl -I https://hasira.natron-company.co/up
```

The `/up` request should return HTTP 200. Then open the website, sign in, test one gatekeeper status change, test its revert, and download a queue report.

If Laravel returns HTTP 500, inspect the latest error with:

```bash
tail -n 100 storage/logs/laravel.log
```

After every future code upload, run:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize
```

The compiled Vite assets in `public/build` are committed to Git because the cPanel
server does not build Node assets. Before committing frontend changes, run
`npm ci && npm run build` locally and include the updated `public/build` files in
the commit. Otherwise PHP and Blade changes may deploy while JavaScript and CSS
remain on an older version.
