# Production Deployment

This is a controlled-launch checklist for LUT Web. It contains placeholders only; do not place production secrets in this repository.

## Platform Requirements

- PHP: use the version allowed by `composer.json` (`^8.3`) and supported by the host.
- Required PHP extensions: PDO driver for the selected database, Fileinfo, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON, Curl, BCMath where enabled, EXIF, and either GD or Imagick with JPEG, PNG, and WebP support.
- FFmpeg must be installed by the operator and include the `lut3d` filter with `tetrahedral` interpolation.
- Database: PostgreSQL or MySQL/MariaDB is recommended for production. SQLite is only acceptable when explicitly approved for a tiny launch.
- Cache/session/queue: use Redis or another durable production service. Do not use array cache/session or sync queue for live traffic.

## Storage

- Public derivatives are stored on the configured public disk and may be cached when filenames are content-hashed.
- Private ProductFiles, purchased Custom LUT builds, customer photos, storefront source masters, and normalized private masters must remain on private disks.
- Run `php artisan storage:link` only for the public disk when local public storage is used.
- Never symlink `storage/app/private` or any private disk root into `public/`.

## Build And Release

Use an equivalent release sequence:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
php artisan production:doctor --strict
```

Route caching must be verified on every release. If route closures are added later, either replace them with controllers or document the exact limitation before launch.

## Workers And Scheduler

Run separate worker groups under a process supervisor:

- Payments: `payments` queue, conservative retries, no long image jobs.
- Images and builds: `images,builds` queues, longer timeout, concurrency based on CPU and memory.
- Default notifications and maintenance: `default,notifications` queues.

Run one scheduler strategy, either `php artisan schedule:work` under a supervisor or cron invoking `php artisan schedule:run` every minute. Do not run both on the same node unless the deployment is intentionally designed for that.

## Web Server

- Terminate TLS before Laravel and set `APP_URL` and `SEO_CANONICAL_URL` to the HTTPS canonical domain.
- Configure `APP_ALLOWED_HOSTS` with the canonical host and intentional aliases only.
- Configure trusted proxies according to the platform. Do not trust arbitrary `X-Forwarded-Host`.
- Serve fingerprinted public derivatives with `Cache-Control: public, max-age=31536000, immutable`.
- Do not apply immutable caching to HTML, account, checkout, signed image, or authenticated Inertia responses.

## PayPal Launch

- Keep PayPal sandbox enabled until merchant onboarding, live client ID, live secret, and webhook ID are confirmed.
- Configure the webhook route at `/webhooks/paypal`.
- Run `php artisan paypal:doctor` and `php artisan production:doctor --strict`.
- Do not enable live payments until legal, tax, delivery, mail, backups, queues, and health checks are green.

## Legal, Tax, SEO, And Mail

- Finalize Terms, Privacy, Terms of Sale, License, Refund Policy, digital-delivery consent, seller country, and tax readiness before live sales.
- Keep `SEO_INDEXING_ENABLED=false` on staging.
- Enable indexing only after canonical HTTPS, robots, sitemap, and product publication state are verified.
- Configure a real transactional mail provider. Emails must be queued and must not attach ZIP, CUBE, PDF, customer images, private paths, or PayPal secrets.

## Backups And Operations

- Back up the database, private ProductFiles, purchased Custom LUT builds, storefront source masters, public derivatives, document records, encryption key, and configuration secrets.
- Exclude expired temp work directories and expired customer test uploads according to retention policy.
- Rotate logs and confirm sensitive fields are redacted.
- After every deploy, run health checks: `/health/live`, `/health/ready`, `php artisan schedule:list`, `php artisan route:list`, and `php artisan production:doctor --strict`.

## Rollback Notes

Code rollback is straightforward when migrations are additive. Database rollback is not always safe after production writes. For destructive schema changes, prepare a tested restore plan first.

## Final Smoke Test

Confirm: admin login, product cover generation, product example Before/After generation, PayPal sandbox checkout, secure account download, robots, sitemap, queued email, audit event creation, and private-file denial without entitlement.
