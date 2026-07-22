# План реализации технических пакетов preview-каталога

> **Для agentic workers:** ОБЯЗАТЕЛЬНЫЙ SUB-SKILL: использовать `superpowers:executing-plans` и выполнять пункты последовательно. Подагенты не используются по правилам текущей сессии.

**Цель:** Создать для всех 300 preview-LUT валидные приватные пакеты CUBE 17/33/65 и текущие Ready-версии без включения продаж или создания фиктивных транзакционных данных.

**Архитектура:** Новый `GenerateStorefrontPreviewPackage` строит детерминированные файлы из параметров `StorefrontPreviewCatalog`, проверяет CUBE/FFmpeg/ZIP, потоково сохраняет пять `ProductFile` на private disk и переключает одну content-addressed `ProductVersion` в current. `StorefrontPreviewMediaSeeder` выполняет cover → package → example.

**Технологии:** PHP 8.5, Laravel 13, Pest 4, Eloquent, Laravel Filesystem, ZipArchive, FFmpeg 6.1, SQLite.

## Глобальные ограничения

- Не добавлять зависимости и миграции.
- Не создавать production users, orders, payments, entitlements, webhook/download/audit events.
- Не включать checkout, PayPal, SMTP или Custom LUT commerce и не подставлять фиктивные credentials/legal approvals.
- Использовать только private disk и content-addressed prefix `products/storefront-preview`.
- Использовать `/www/server/ffmpeg/ffmpeg-6.1/ffmpeg` через существующую конфигурацию.
- Соблюдать RED/GREEN, Laravel model invariants и `vendor/bin/pint --dirty --format agent`.
- Перед production seed создать и проверить датированную SQLite backup-копию.

---

### Задача 1: RED — поведение package action

**Файлы:**
- Изменить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`

**Интерфейсы:**
- Проверяет будущий `GenerateStorefrontPreviewPackage::handle(Product $product, array $entry): ProductVersion`.

- [x] Добавить тест для `PREVIEW-TRAVEL-001` с fake private disk и реальным FFmpeg.
- [x] Ожидать одну Ready/current версию и пять файлов `cube_17`, `cube_33`, `cube_65`, `readme`, `package_zip`.
- [x] Проверить физическое существование, private paths, SHA-256/size metadata, CUBE directives и безопасный состав ZIP.
- [x] Повторить action и проверить неизменность version/file ID и storage paths.
- [x] Запустить узкий тест и подтвердить RED только из-за отсутствующего action.

### Задача 2: GREEN — детерминированный package action

**Файлы:**
- Создать: `app/Actions/Storefront/GenerateStorefrontPreviewPackage.php`
- Изменить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`

**Интерфейсы:**
- Потребляет `WriteCubeFile`, `ValidateGeneratedCube`, `ValidateCubeWithFfmpeg`, `CreateCustomLutPackageZip`, `SetCurrentProductVersion`, `Product`, catalog entry.
- Производит `handle(Product $product, array $entry): ProductVersion`.

- [x] Создать action через `php artisan make:class Actions/Storefront/GenerateStorefrontPreviewPackage --no-interaction`.
- [x] Реализовать fingerprint из SKU, parameters hash, transform/generator version, cube sizes/precision и ZIP schema.
- [x] Создать и проверить CUBE 17/33/65; FFmpeg применить к CUBE 33.
- [x] Создать детерминированные README, manifest и checksums без draft/legal фикций.
- [x] Собрать и повторно проверить ZIP через существующий safe ZIP-builder.
- [x] Потоково и атомарно сохранить пять файлов на private disk.
- [x] В транзакции создать Ready version/files и сделать версию единственной current.
- [x] При ошибке удалить только новые storage paths и неприобретённую новую версию; всегда удалить work-каталог.
- [x] Запустить узкий тест, Pint и Larastan; подтвердить GREEN.

### Задача 3: Seeder orchestration и полный изолированный прогон

**Файлы:**
- Изменить: `database/seeders/StorefrontPreviewMediaSeeder.php`
- Изменить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`

**Интерфейсы:**
- Seeder получает `GenerateStorefrontPreviewCover`, `GenerateStorefrontPreviewPackage`, `GenerateStorefrontPreviewExample` через constructor injection.

- [x] Расширить mock-тест: для каждого из 300 товаров ожидать порядок cover → package → example.
- [x] Подтвердить RED при отсутствии package-вызовов.
- [x] Подключить package action между cover и example, обновить progress message.
- [x] Подтвердить GREEN узкого теста.
- [x] На временной SQLite/private root выполнить package action для всех 300 товаров дважды.
- [x] Подтвердить 300 Ready/current versions, 1500 ProductFiles, физические файлы, стабильные ID/paths и нули operational/commerce таблиц.

### Задача 4: Полная проверка ветки

- [x] Запустить `vendor/bin/pint --dirty --format agent`.
- [x] Запустить `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`.
- [x] Запустить целевые тесты и полный `php artisan test --compact` с временным APP_KEY.
- [x] Запустить `npm run test:lut-transform`, `npm run lint:check`, `npm run types:check`, `npm run format:check`, `npm run build`.
- [x] Проверить `git diff --check`, отсутствие secrets/runtime-файлов и закоммитить source/tests/docs.

Фактический изолированный результат: SQLite integrity `ok`; 300 testable products; 300 Ready/current versions; 1500 `ProductFile`; 1500 физических private-файлов (4,5 ГБ); ноль временных файлов и ноль operational/commerce rows. Повторный полный media seed завершился за 14 секунд, а контрольный hash ID/version/file/path/SHA остался `e26375f7a5775b74de177d0c28e2d7f767ec1ce4e3c7e6ceee2b5f6774029bc9`. Полный Pest: 308 тестов, 306 passed, 2 skipped, 2212 assertions; Larastan: 0 ошибок; frontend checks и build прошли.

### Задача 5: Production backup, seed и live QA

- [x] Создать `/www/backup/lutweb/database/database-before-preview-packages-YYYYMMDD_HHMMSS.sqlite`, проверить integrity и SHA-256.
- [x] Остановить четыре `lutweb-*` сервиса с гарантированным restart trap.
- [x] От `www` запустить `StorefrontPreviewMediaSeeder` дважды и восстановить framework cache.
- [x] Проверить 300 Ready/current versions, 1500 ProductFiles, private physical files и нулевые operational/commerce таблицы.
- [x] Запустить doctor-команды; PackageZip warning должен исчезнуть, media doctor остаётся чистым.
- [x] Проверить product detail и tester через Playwright desktop/mobile, console/network/overflow.
- [x] Обновить план фактическими результатами, отправить `main` в GitHub и подтвердить совпадение tree.

Фактический production-результат:

- Проверенная backup-копия: `/www/backup/lutweb/database/database-before-preview-packages-20260722_200957.sqlite`, 5 398 528 байт, SQLite integrity `ok`, SHA-256 `b2f0d628fb99d97750341d73b197cf7b1d60cdc2f5a2e677a75d6ab4f18ebe81`.
- После полного seed и двух повторных idempotency-прогонов: 300 published/testable products, 300 Ready/current versions, 1500 `ProductFile`, 300 Ready examples, 300 Ready media и 7200 responsive variants.
- На private storage лежат ровно 1500 файлов объёмом 4,5 ГБ; missing files, metadata mismatches, temporary files и unsafe/invalid ZIP — по нулям. Каждый из 300 ZIP содержит шесть безопасных entries.
- Во всех operational/commerce таблицах остался ноль строк; PayPal, checkout, SMTP и Custom LUT commerce остались намеренно выключены.
- `storefront-media:doctor`, `paypal:doctor`, `custom-lut:doctor`, `custom-lut-commerce:doctor`, LUT doctor, wizard doctor и SEO doctor не нашли package/media ошибок. Ожидаемые production-предупреждения о SQLite, отсутствующих внешних credentials/legal approvals, log mailer и желательном Imagick не маскировались.
- Все четыре systemd-сервиса `lutweb-default`, `lutweb-images`, `lutweb-payments`, `lutweb-scheduler` активны; приложение выведено из maintenance mode.
- Playwright на 1440×1100 и 390×844 подтвердил отсутствие горизонтального overflow, видимые 17/33/65 CUBE, README и ZIP, успешную загрузку всех preview-изображений, нуль console errors/warnings и корректный guest redirect с `Try on Your Photo` на `/login`.

Контроль после синхронизации с опубликованным `main`:

- Перед повторным rollout создана backup-копия `/www/backup/lutweb/database/database-before-current-main-media-20260722_213620.sqlite`: 6 057 984 байт, integrity `ok`, SHA-256 `6eb7e08c17b436d52bd9a8c4170d27fb146a64c5cdf61e19313fba891b56d3a9`.
- Четыре контролируемых чанка создали 300 актуальных cover/package/example наборов; два последовательных media seed дошли до 300/300 без изменений ID/path.
- Удалены ровно 300 старых неприобретённых версий и 1500 их файлов через Eloquent model events. Финально: 300/300 fingerprints совпадают с текущим каталогом, 300 Ready/current versions, 1500 записей и 1500 физических файлов (4,4 ГБ), orphan package-файлов нет.
- Независимо прочитаны все 1500 файлов: missing, size mismatch и SHA-256 mismatch равны нулю. Все 300 ZIP прошли `ZipArchive::CHECKCONS`, имеют ровно шесть entries и не содержат unsafe paths.
- Финальный полный Pest в изолированной in-memory SQLite: 316 тестов, 314 passed, 2 skipped, 11 039 assertions. Larastan: 0 ошибок; LUT Transform: 61 кейс; type check, Prettier, scoped ESLint и production build прошли.
- Повторный live Playwright после rollout подтвердил desktop/mobile overflow `false`, загрузку cover/Before/After/related previews, console 0 errors/0 warnings и guest redirect tester на `/login`.
