# Backup And Restore

This document describes what must be protected before a controlled production launch. It contains no backup credentials.

## Back Up

- Database: application tables, migrations, orders, payments, entitlements, package-document records, audit events, and notification dispatch records.
- Private ProductFiles: ZIP packages, CUBE files, guides, licenses, and readme files.
- Purchased Custom LUT builds and their private delivery artifacts.
- Private storefront source masters under the configured storefront source prefix.
- Public storefront derivatives. These can be regenerated, but backing them up speeds restore and avoids a cold image queue.
- Application encryption key and configuration secrets in the deployment secret store.
- PayPal configuration references: mode, client ID, webhook ID, and merchant setup notes. Store secrets only in the secret manager.
- Mail provider configuration references. Store SMTP/API secrets only in the secret manager.

## Exclude

- Expired temporary customer photo data.
- Abandoned image, LUT, and build work directories.
- Runtime caches, queue database files, local logs, test databases, and generated development artifacts.

## Protection

- Encrypt backups before offsite storage.
- Keep a retention policy appropriate to order, tax, support, and privacy obligations.
- Restrict restore access to trusted operators.
- Test restore periodically into an isolated non-production environment.

## Restore Sequence

1. Pause queue workers and scheduler.
2. Restore application code matching the backup window.
3. Restore `.env` values from the secret store, including the same `APP_KEY`.
4. Restore the database.
5. Restore private disks before enabling downloads.
6. Restore public derivatives or run controlled regeneration.
7. Rebuild caches with `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, and `php artisan event:cache`.
8. Run `php artisan migrate --force` only if the restored code requires unapplied additive migrations.
9. Run `php artisan production:doctor --strict`.
10. Resume scheduler and workers with `php artisan queue:restart`.

## Integrity Checks

- Confirm no known default users exist.
- Confirm private ProductFiles and Custom LUT ZIP files are not publicly reachable.
- Confirm purchased entitlements can download through authenticated routes.
- Confirm health readiness returns 200 only when queue and scheduler heartbeats are current.
- Confirm PayPal webhook processing is idempotent after replay.
- Confirm audit logs survived restore.
