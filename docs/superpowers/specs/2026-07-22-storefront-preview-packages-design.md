# Дизайн технических пакетов preview-каталога

## Цель

Сделать все 300 опубликованных preview-LUT технически полноценными: у каждого товара должны появиться валидные CUBE 17/33/65, приватный ZIP-пакет и текущая Ready-версия. Это включает поддержку `Try on Your Photo` и корректный список содержимого пакета, но не включает включение продаж.

## Исходное состояние

- В production существуют 300 опубликованных товаров, 300 Ready covers и 300 Ready Before/After examples.
- У товаров отсутствуют `ProductVersion` и `ProductFile`, поэтому интерфейс сообщает, что downloadable package не готов.
- Checkout, PayPal и Custom LUT commerce отключены; SMTP использует `log`; юридические package templates остаются draft.
- Все 30 моделей имеют factory. Справочные данные сидируются в production, а транзакционные модели заполняются только защищёнными `LocalDemo*Seeder` в `local/testing`.

## Рассмотренные подходы

### 1. Отдельный генератор catalog package — выбран

Новый action работает с `Product` и записью `StorefrontPreviewCatalog`, переиспользует низкоуровневые проверенные компоненты CUBE/FFmpeg/ZIP и сохраняет результат в существующие `ProductVersion` и `ProductFile`.

Преимущества: чистая доменная модель, реальная проверка файлов, детерминированность, отсутствие фиктивных пользователей и wizard-проектов.

### 2. Временные `CustomLutBuild` и wizard-записи — отклонён

Это позволило бы вызвать `GenerateCustomLutPackage`, но потребовало бы фиктивных пользователей, проектов и commerce-метаданных в production. Такой способ нарушает границы домена и правила безопасного сидирования.

### 3. Ручная загрузка готовых файлов — отклонён

Для 300 товаров потребуется не менее 1200 ручных загрузок. Подход неидемпотентен, не проверяет соответствие параметров каталога и непригоден для повторного развёртывания.

## Архитектура

`GenerateStorefrontPreviewPackage::handle(Product $product, array $entry): ProductVersion`:

1. Проверяет SKU, параметры каталога, private disk, конфигурацию размеров `[17, 33, 65]` и вычисляет SHA-256 fingerprint.
2. Ищет версию `preview-<12 символов fingerprint>` и возвращает её без изменений, если она Ready/current, содержит ровно пять ожидаемых `ProductFile`, а физические файлы существуют и совпадают с сохранёнными SHA-256.
3. Во временном private work-каталоге создаёт CUBE 17/33/65 через `WriteCubeFile`.
4. Каждый CUBE проходит `ValidateGeneratedCube`; CUBE 33 дополнительно проходит реальную проверку настроенным FFmpeg 6.1. Проверка одного репрезентативного размера ограничивает production-время без снижения синтаксической проверки остальных размеров.
5. Создаёт UTF-8 README с явным preview/non-sale предупреждением, JSON manifest и `CHECKSUMS.txt`.
6. Создаёт и повторно валидирует детерминированный ZIP через `CreateCustomLutPackageZip`.
7. Потоково сохраняет CUBE 17/33/65, README и ZIP в private disk под content-addressed prefix `products/storefront-preview/<sku>/<fingerprint>/`.
8. В транзакции создаёт Ready `ProductVersion`, пять `ProductFile` и делает новую версию единственной current через `SetCurrentProductVersion`.
9. При ошибке удаляет только новые storage paths и новую неприобретённую версию; временный каталог удаляется всегда.

Каталог сохраняет preview-LUT с `is_testable=true`. Публичный `Try on Your Photo` при этом появляется только после создания Ready current version и поддерживаемого private CUBE, потому что окончательное решение принимает `ProductLutTestEligibility`.

## Состав пакета

- `CUBE/<slug>-17.cube`
- `CUBE/<slug>-33.cube`
- `CUBE/<slug>-65.cube`
- `README.txt`
- `manifest.json`
- `CHECKSUMS.txt`

README прямо сообщает, что это технический preview-пакет и продажа должна оставаться отключённой до production SMTP, PayPal credentials и подтверждённых юридических документов.

## Интеграция с сидером

`StorefrontPreviewMediaSeeder` вызывает действия в порядке:

1. cover;
2. package;
3. example.

Создание/переключение `ProductVersion` через существующие model events помечает examples как Stale. Последующий вызов example action восстанавливает корректный Ready fingerprint и не оставляет doctor-предупреждений.

## Безопасность и эксплуатация

- Все package-файлы находятся только на private disk и не получают публичных URL.
- `Try on Your Photo` использует CUBE 33 через существующий защищённый LUT tester и не раскрывает private path.
- Не создаются пользователи, заказы, платежи, entitlements, webhook events и download events.
- Не включаются `CHECKOUT_ENABLED`, `PAYPAL_ENABLED` и Custom LUT commerce.
- Content-addressed paths и SHA-256 защищают от частичной повторной генерации.
- Существующие приобретённые версии/файлы никогда не изменяются и не удаляются.
- Перед production seed создаётся проверенная внешняя SQLite backup-копия, сервисы останавливаются с гарантированным restart trap.

## Проверка результата

- TDD-тест подтверждает RED до action и GREEN после реализации.
- Узкий тест проверяет пять DB-файлов, физическое private storage, CUBE directives, ZIP entries, checksums, Ready/current и неизменность ID при повторе.
- Seeder mock-тест подтверждает 300 вызовов cover/package/example в правильном порядке.
- Изолированный полный прогон подтверждает 300 Ready/current versions, 1500 ProductFiles и физические файлы без operational/commerce data.
- Полный Pest, Pint, Larastan, frontend checks и build должны пройти.
- Production `paypal:doctor` должен перестать предупреждать об отсутствии PackageZip; `storefront-media:doctor` должен сохранить нули для missing/stale/failed/orphaned.
- Playwright desktop/mobile должен показать список CUBE 17/33/65, активный `Try on Your Photo`, отсутствие overflow и ошибок консоли/сети.
