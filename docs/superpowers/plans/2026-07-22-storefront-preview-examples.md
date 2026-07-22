# Storefront Preview Examples Implementation Plan

> **Для agentic workers:** ОБЯЗАТЕЛЬНЫЙ SUB-SKILL: использовать `superpowers:executing-plans` и выполнять пункты последовательно. Подагенты не используются по правилам текущей сессии.

**Цель:** Создать по одному идемпотентному Ready Before/After example для каждого из 300 preview-LUT без фиктивных коммерческих файлов.

**Архитектура:** Новый action создаёт или обновляет `ProductExample`, генерирует CUBE 17 из параметров детерминированного каталога, применяет его через проектный FFmpeg и передаёт водяные Before/After в существующий responsive generator. `StorefrontPreviewMediaSeeder` оркестрирует cover и example для каждой записи.

**Технологии:** PHP 8.5, Laravel 13, Pest 4, Eloquent, Intervention Image, FFmpeg 6.1, SQLite, Playwright CLI.

## Глобальные ограничения

- Не добавлять зависимости, миграции, пользователей, заказы, платежи, entitlements, product versions или product files.
- Не коммитить `.env`, production SQLite, runtime storage, `.user.ini` и `.well-known`.
- Использовать только `/www/server/ffmpeg/ffmpeg-6.1/ffmpeg` из проектной конфигурации.
- Для PHP соблюдать RED/GREEN и запускать `vendor/bin/pint --dirty --format agent`.
- Перед production seed создать и проверить датированную SQLite backup-копию.

---

### Задача 1: RED — поведение preview example

**Файлы:**
- Изменить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`

**Интерфейсы:**
- Потребляет: `StorefrontPreviewCatalog::entries()` и продукт `PREVIEW-TRAVEL-001`.
- Проверяет будущий `GenerateStorefrontPreviewExample::handle(Product $product, array $entry): ProductExample`.

- [x] Добавить тест, который вызывает будущий action и ожидает один Ready example с пустыми legacy paths, подтверждёнными правами, null version/file и 16 вариантами ролей Before/After.
- [x] Для каждого варианта проверить существование файла на fake public disk.
- [x] Повторно вызвать action и проверить неизменность example ID и variant IDs.
- [x] Запустить `php artisan test --compact tests/Feature/StorefrontPreviewMediaSeederTest.php` с временным APP_KEY и подтвердить RED из-за отсутствующего action.

### Задача 2: GREEN — генератор preview example

**Файлы:**
- Создать: `app/Actions/Storefront/GenerateStorefrontPreviewExample.php`
- Проверить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`

**Интерфейсы:**
- Потребляет: `WriteCubeFile`, `ApplyPreviewWatermark`, `GenerateStorefrontImageVariants`, `DeleteStorefrontImageVariants`, `Product`, catalog entry.
- Производит: `handle(Product $product, array $entry): ProductExample`.

- [x] Создать action через `php artisan make:class Actions/Storefront/GenerateStorefrontPreviewExample --no-interaction`.
- [x] Реализовать проверку исходника, SHA-256, размеров и конфигурации responsive widths.
- [x] Идемпотентно находить пример по product и устойчивому title; проверять fingerprint, Ready status, роли, физические файлы и публичность ожидаемых вариантов.
- [x] Сохранять source/rights metadata, пустые legacy paths и null commercial foreign keys.
- [x] Во временном каталоге создать CUBE 17, получить graded через FFmpeg, нанести watermark на Before/After и сгенерировать по восемь вариантов на роль.
- [x] При ошибке удалять только новые варианты, не удаляя совпадающие пути старого успешного набора; отмечать example как Failed, писать безопасный log context и всегда удалять work-каталог.
- [x] Запустить узкий тест и подтвердить GREEN: 3 теста, 59 утверждений.

### Задача 3: Seeder и полный изолированный прогон

**Файлы:**
- Изменить: `database/seeders/StorefrontPreviewMediaSeeder.php`
- Изменить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`

**Интерфейсы:**
- Seeder получает `GenerateStorefrontPreviewCover` и `GenerateStorefrontPreviewExample` через constructor injection.

- [x] Добавить в тест проверку, что media seeder вызывает example вместе с cover для всех 300 записей каталога.
- [x] Запустить тест и подтвердить RED: example action ожидался 300 раз, но не был вызван.
- [x] Вызывать example action сразу после cover action и выводить объединённый прогресс `Generated preview covers and examples`.
- [x] Запустить узкий тест и подтвердить GREEN.
- [x] На временной SQLite и отдельном public prefix выполнить реальный example action для всех 300 товаров дважды; полный порядок media seeder отдельно подтверждён мок-тестом без повторного кодирования covers.
- [x] Подтвердить 300 products, 300 Ready examples, 4800 физических example variants (2400 Before + 2400 After), а также неизменность всех example/variant ID после второго прохода.

### Задача 4: Полная проверка ветки

- [x] Запустить `vendor/bin/pint --dirty --format agent`.
- [x] Запустить `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`: ошибок нет.
- [x] Запустить целевые тесты и полный `php artisan test --compact` с временным APP_KEY: 307 тестов, 305 успешных, 2 пропущены, 1828 утверждений.
- [x] Запустить `npm run test:lut-transform`, `npm run lint:check`, `npm run types:check`, `npm run format:check`, `npm run build`: все проверки успешны; LUT Transform V1 — 61 кейс.
- [x] Проверить `git diff --check`, отсутствие secrets/runtime-файлов и закоммитить исходники/tests/docs.

### Задача 5: Production backup, seed и live QA

- [x] Создать `/www/backup/lutweb/database/database-before-preview-examples-20260722_183909.sqlite`: `integrity_check = ok`, SHA-256 `bcf904e26e13a6217ca4ec706133abaa7909bf2e250a96ea4a6af63505c26ca2`.
- [x] Остановить `lutweb-default`, `lutweb-images`, `lutweb-payments`, `lutweb-scheduler` с гарантированным restart trap; после операции все четыре сервиса имеют статус `active`.
- [x] От `www` запустить `StorefrontPreviewMediaSeeder` дважды и восстановить framework cache.
- [x] Проверить production: 300 published products, 300 Ready covers, 300 Ready examples, 4800 example variants, 7200 общих variant records и 7200 физических файлов; `product_versions`, `product_files` и `users` равны нулю; SQLite integrity — `ok`.
- [x] Запустить `storefront-media:doctor`: missing Ready covers/examples, missing rights, failed/stale, legacy direct paths и orphaned variants равны нулю.
- [x] Проверить `/shop/alpine-morning-travel-lut` и `/luts/travel` через Playwright desktop/mobile: HTTP 200, обе Before/After картинки загружены, slider и side-by-side работают, mobile figures складываются вертикально, overflow/console/page/network errors отсутствуют.
- [x] Отправить `main` в `origin`, подтвердить совпадение commit/tree и закрыть все чекбоксы плана.
