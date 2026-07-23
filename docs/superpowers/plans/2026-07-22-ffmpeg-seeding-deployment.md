# FFmpeg 6.1, Seeders и публикация — план реализации

> **Для agentic workers:** ОБЯЗАТЕЛЬНЫЙ SUB-SKILL: использовать `superpowers:executing-plans` для последовательного выполнения этого плана. Шаги отслеживаются checkbox-ами.

**Цель:** Настроить LUT Web на серверный FFmpeg 6.1 через env/config, доказать полноту seeders для всех моделей, опубликовать содержательные изменения и воспроизводимо пересоздать production SQLite.

**Архитектура:** Laravel продолжает получать FFmpeg только через существующие `config/lut-tester.php` и `config/custom-lut-builds.php`; абсолютный путь задаётся окружением. Production seed остаётся безопасным baseline, а полный граф всех Eloquent-моделей проверяется защищённым от production `LocalDemoApplicationSeeder` на тестовой базе.

**Технологии:** PHP 8.5, Laravel 13, Pest 4, SQLite, FFmpeg 6.1, systemd, Nginx, Git.

## Глобальные ограничения

- Использовать только `/www/server/ffmpeg/ffmpeg-6.1/ffmpeg` и только для этого проекта.
- Не добавлять зависимости.
- Не публиковать `.env`, секреты, `lutweb.zip`, `.user.ini`, `.well-known` и runtime-артефакты.
- Не публиковать случайные изменения прав `100644 -> 100755`.
- Перед разрушительным reseed создать датированную резервную копию точного SQLite-файла.
- Demo seeders не должны запускаться в production.

## Текущий статус

- [x] FFmpeg 6.1 настроен через `.env` и оба шаблона окружения; конфигурационный тест и doctor-команды проходят.
- [x] Все 28 моделей покрыты полным local/testing seed-графом; отсутствовавший `PackageDocumentTemplateSeeder` подключён.
- [x] Нормализованы 727 случайных изменений прав файлов из ZIP.
- [x] E2E-контур включён в TypeScript/ESLint, изолирован от production SQLite и проверен полным набором из 20 браузерных сценариев.
- [x] Composer lock, PHPUnit memory limit, PHPStan, Pest, frontend lint/type/format/conformance и production build проверены.
- [x] Создать проверенный commit и отправить `main` в `origin`.
- [x] Создать резервную копию, выполнить production reseed и проверить baseline.
- [x] Завершить runtime-проверки и зафиксировать итоговый статус.

Внешние launch-ограничения, которые не заполняются фиктивными данными: production SMTP, PayPal credentials и включение checkout, финальное юридическое/налоговое подтверждение. Реальный каталог из 300 LUT уже опубликован и проверен; SQLite используется намеренно для текущего малонагруженного запуска и подтверждается только production-флагом `DB_PRODUCTION_SQLITE_APPROVED=true`, тогда как оба публикуемых env-шаблона остаются fail-closed.

### Итог выполнения

- Опубликованный commit реализации: `66dda909b4b214757a6f07368419839a621b3517` (`main == origin/main`).
- Резервная копия до reseed: `/www/backup/lutweb/database/database-before-reseed-20260722_151923.sqlite`.
- SHA-256 резервной копии: `84236b591d9f8696b7d589c395e87061e8636432c73f6205ad81369b5df47579`; `integrity_check=ok`.
- Production baseline: 14 категорий, 15 тегов, 5 приложений, 6 Wizard Styles, 2 шаблона документов и 1 commerce-setting; users/products/orders/payments/entitlements — 0.
- Все четыре systemd-службы включены и активны; HTTP перенаправляет на HTTPS, `/`, `/up`, `/health/live`, `/health/ready` и production asset возвращают 200.

---

### Задача 1: Env-конфигурация FFmpeg 6.1

**Файлы:**
- Изменить: `tests/Feature/OperationalReadinessTest.php`
- Изменить: `.env`
- Изменить: `.env.example`
- Изменить: `deploy/.env.production.example`

**Интерфейсы:**
- Использует: `env('LUT_TESTER_FFMPEG_BINARY')`, `env('CUSTOM_LUT_FFMPEG_BINARY')`.
- Создаёт: стабильные значения `lut-tester.ffmpeg_binary` и `custom-lut-builds.ffmpeg_binary` для web и queue процессов.

- [x] **Шаг 1: Добавить падающий тест шаблонов конфигурации**

```php
test('ffmpeg consumers use the project environment binary', function (): void {
    $binary = '/www/server/ffmpeg/ffmpeg-6.1/ffmpeg';
    $environmentExample = File::get(base_path('.env.example'));
    $productionExample = File::get(base_path('deploy/.env.production.example'));

    expect($environmentExample)
        ->toContain('LUT_TESTER_FFMPEG_BINARY='.$binary)
        ->toContain('CUSTOM_LUT_FFMPEG_BINARY='.$binary)
        ->and($productionExample)
        ->toContain('LUT_TESTER_FFMPEG_BINARY='.$binary)
        ->toContain('CUSTOM_LUT_FFMPEG_BINARY='.$binary)
        ->and(File::get(config_path('lut-tester.php')))
        ->toContain("env('LUT_TESTER_FFMPEG_BINARY', 'ffmpeg')")
        ->and(File::get(config_path('custom-lut-builds.php')))
        ->toContain("env('CUSTOM_LUT_FFMPEG_BINARY', 'ffmpeg')");
});
```

- [x] **Шаг 2: Запустить тест и подтвердить ожидаемое падение**

Команда: `php artisan test --compact tests/Feature/OperationalReadinessTest.php --filter='ffmpeg consumers'`

Ожидается: FAIL, потому что `.env.example` содержит `ffmpeg`, а production-шаблон не содержит обоих ключей.

- [x] **Шаг 3: Настроить env-файлы**

Добавить одинаковые значения:

```dotenv
LUT_TESTER_FFMPEG_BINARY=/www/server/ffmpeg/ffmpeg-6.1/ffmpeg
CUSTOM_LUT_FFMPEG_BINARY=/www/server/ffmpeg/ffmpeg-6.1/ffmpeg
```

- [x] **Шаг 4: Пересобрать config cache и проверить тест**

Команды:

```bash
runuser -u www -- php artisan optimize
runuser -u www -- php artisan queue:restart
php artisan test --compact tests/Feature/OperationalReadinessTest.php --filter='ffmpeg consumers'
php artisan lut:doctor --no-interaction
php artisan custom-lut:doctor --self-test --no-interaction
```

Ожидается: тест PASS; doctor-команды не содержат FAIL и показывают FFmpeg 6.1/lut3d.

### Задача 2: Полнота seeders для всех моделей

**Файлы:**
- Изменить: `tests/Feature/LocalDemoSeedersTest.php`
- Изменить: `database/seeders/LocalDemoApplicationSeeder.php`

**Интерфейсы:**
- Использует: production reference seeders и существующие LocalDemo seeders.
- Создаёт: тестовый seed-граф, покрывающий каждый класс из `app/Models` без ослабления production-защиты.

- [x] **Шаг 1: Расширить падающий тест полного model graph**

Добавить в список проверяемых моделей:

```php
Category::class,
CompatibleSoftware::class,
CustomLutCommerceSetting::class,
PackageDocumentTemplate::class,
Tag::class,
WizardStyle::class,
```

Также сравнить отсортированный список классов с файлами `app/Models/*.php`, чтобы будущая модель без seed-а делала тест красным.

- [x] **Шаг 2: Подтвердить падение**

Команда: `php artisan test --compact tests/Feature/LocalDemoSeedersTest.php`

Ожидается: FAIL для `PackageDocumentTemplate`, потому что `LocalDemoApplicationSeeder` ещё не вызывает его seeder.

- [x] **Шаг 3: Добавить production-safe reference seeder в demo aggregate**

В `LocalDemoApplicationSeeder::run()` после `WizardStyleSeeder::class` добавить:

```php
PackageDocumentTemplateSeeder::class,
```

- [x] **Шаг 4: Проверить полный seed-граф и production guard**

Команды:

```bash
php artisan test --compact tests/Feature/LocalDemoSeedersTest.php
php artisan test --compact tests/Feature/UserSeederTest.php tests/Feature/CatalogDomainTest.php tests/Feature/WizardStyleTest.php tests/Feature/PackageDocumentTemplateTest.php
```

Ожидается: все тесты PASS; production demo guard остаётся активным; default users не создаются.

### Задача 3: Аудит текущих исходников и спецификации

**Файлы:**
- Изменить: `docs/production-deployment.md`
- Проверить: `.gitignore`, `composer.json`, `package.json`, `package-lock.json`, `tests/Feature/OperationalReadinessTest.php`
- Проверить новые: `app/Console/Commands/E2ePrepare.php`, `playwright.config.ts`, `tests/e2e/**`

**Интерфейсы:**
- Использует: текущий Git diff и doctor-команды.
- Создаёт: точный список publishable файлов и список внешних launch-ограничений.

- [x] **Шаг 1: Нормализовать только случайные mode changes**

Для каждого пути из `git diff --summary`, отмеченного `mode change 100644 => 100755`, выполнить `chmod 0644` и повторно проверить `git diff --summary`.

- [x] **Шаг 2: Документировать production FFmpeg env**

В `docs/production-deployment.md` рядом с требованием `lut3d` указать, что этот deployment задаёт оба env-ключа абсолютным путём:

```dotenv
LUT_TESTER_FFMPEG_BINARY=/www/server/ffmpeg/ffmpeg-6.1/ffmpeg
CUSTOM_LUT_FFMPEG_BINARY=/www/server/ffmpeg/ffmpeg-6.1/ffmpeg
```

- [x] **Шаг 3: Проверить каждый содержательный diff и новый файл**

Команды:

```bash
git diff --check
git diff -- .gitignore composer.json package.json docs/production-deployment.md tests/Feature/OperationalReadinessTest.php
git status --short
```

Ожидается: server/runtime-файлы исключены; исходники e2e, конфигурация, тесты, seeders и документация не содержат секретов.

- [x] **Шаг 4: Запустить проверки спецификации**

Команды:

```bash
php artisan production:doctor --no-interaction
php artisan lut-wizard:doctor --no-interaction
php artisan custom-lut-commerce:doctor --no-interaction
php artisan storefront-media:doctor --no-interaction
php artisan seo:doctor --no-interaction
php artisan mail:doctor --no-interaction
php artisan paypal:doctor --show-webhook-url --no-interaction
rg -n 'TODO|FIXME|TBD|not implemented|not yet' docs app database routes tests
```

Ожидается: кодовые FAIL исправлены; отсутствие SMTP/PayPal credentials, production DB-сервера, финальных юридических текстов и launch-контента фиксируется как внешнее ограничение.

### Задача 4: Полная проверка до публикации

**Файлы:**
- Проверить: весь проект.

**Интерфейсы:**
- Использует: изменения задач 1–3.
- Создаёт: доказательство готовности коммита.

- [x] **Шаг 1: Форматировать изменённый PHP**

Команда: `vendor/bin/pint --dirty --format agent`

Ожидается: exit 0.

- [x] **Шаг 2: Запустить backend и frontend проверки**

Команды:

```bash
runuser -u www -- php artisan config:clear
php artisan test --compact --parallel
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
npm run test:lut-transform
npm run lint:check
npm run types:check
npm run format:check
npm run build
npx playwright test --list
runuser -u www -- php artisan optimize
```

Ожидается: Pest без FAIL, PHPStan без ошибок, LUT conformance PASS, lint/type-check/format/build exit 0 и 16 Playwright-сценариев успешно перечисляются.

### Задача 5: Чистый commit и push

**Файлы:**
- Публиковать: только проверенные исходники, конфигурационные шаблоны, тесты, seeders и docs.
- Исключить: `.env`, `lutweb.zip`, `.user.ini`, `.well-known`, `storage/**`, runtime cache/logs.

**Интерфейсы:**
- Использует: проверенный diff задачи 4.
- Создаёт: commit в `main` и обновлённый `origin/main`.

- [x] **Шаг 1: Синхронизировать удалённую ветку без перезаписи локальной работы**

Команды:

```bash
git fetch origin main
git status --short --branch
git log --oneline --left-right HEAD...origin/main
```

- [x] **Шаг 2: Явно проиндексировать только утверждённые файлы и проверить staged diff**

Команды:

```bash
git add \
  .env.example \
  .gitignore \
  app/Console/Commands/E2ePrepare.php \
  composer.json \
  composer.lock \
  database/seeders/LocalDemoApplicationSeeder.php \
  deploy/.env.production.example \
  docs/production-deployment.md \
  docs/superpowers/plans/2026-07-22-ffmpeg-seeding-deployment.md \
  docs/superpowers/specs/2026-07-22-ffmpeg-seeding-deployment-design.md \
  package-lock.json \
  package.json \
  phpunit.xml \
  playwright.config.ts \
  tests/e2e \
  tests/Feature/LocalDemoSeedersTest.php \
  tests/Feature/OperationalReadinessTest.php \
  tsconfig.json
git diff --cached --check
git diff --cached --stat
```

- [x] **Шаг 3: Создать commit и отправить main**

```bash
git commit -m "Configure FFmpeg and complete seed coverage"
git push origin main
```

Ожидается: push exit 0; remote commit совпадает с локальным HEAD.

### Задача 6: Резервная копия и production reseed

**Файлы:**
- Источник: `database/database.sqlite`
- Резервная копия: `/www/backup/lutweb/database/database-before-reseed-${reseed_stamp}.sqlite`, где `reseed_stamp=$(date +%Y%m%d_%H%M%S)`.

**Интерфейсы:**
- Использует: опубликованный и проверенный seed-код.
- Создаёт: чистую production SQLite с baseline reference data.

- [x] **Шаг 1: Проверить точный target и остановить workers**

Команды:

```bash
php artisan config:show database.default
php artisan config:show database.connections.sqlite.database
systemctl stop lutweb-default.service lutweb-images.service lutweb-payments.service lutweb-scheduler.service
```

- [x] **Шаг 2: Создать и проверить резервную копию**

```bash
reseed_stamp=$(date +%Y%m%d_%H%M%S)
reseed_backup=/www/backup/lutweb/database/database-before-reseed-${reseed_stamp}.sqlite
install -D -m 0600 database/database.sqlite "$reseed_backup"
sha256sum database/database.sqlite "$reseed_backup"
cmp -s database/database.sqlite "$reseed_backup"
```

Ожидается: одинаковые SHA-256 и `cmp` exit 0.

- [x] **Шаг 3: Выполнить полный reseed и восстановить права**

```bash
runuser -u www -- php artisan migrate:fresh --seed --force --no-interaction
chown www:www database database/database.sqlite
chmod 775 database
chmod 664 database/database.sqlite
```

- [x] **Шаг 4: Запустить сервисы и проверить baseline counts**

Команды:

```bash
systemctl start lutweb-default.service lutweb-images.service lutweb-payments.service lutweb-scheduler.service
php artisan migrate:status --no-interaction
php artisan production:doctor --no-interaction
```

Ожидается: миграции Ran; справочные таблицы заполнены; users/orders/payments/entitlements остаются пустыми; workers active.

### Задача 7: Финальная эксплуатационная проверка

**Файлы:**
- Проверить: production runtime.

**Интерфейсы:**
- Использует: production seed и опубликованный commit.
- Создаёт: итоговый отчёт с проверенными результатами и внешними ограничениями.

- [x] **Шаг 1: Проверить FFmpeg и сервисы**

```bash
php artisan lut:doctor --no-interaction
php artisan custom-lut:doctor --self-test --no-interaction
systemctl is-enabled lutweb-default.service lutweb-images.service lutweb-payments.service lutweb-scheduler.service
systemctl is-active lutweb-default.service lutweb-images.service lutweb-payments.service lutweb-scheduler.service
```

- [x] **Шаг 2: Проверить публичный runtime**

```bash
curl -sS -o /dev/null -w '%{http_code} %{redirect_url}\n' http://luts.miniserver.fun/
curl -sS --fail-with-body -o /dev/null -w '%{http_code}\n' https://luts.miniserver.fun/
curl -sS --fail-with-body -o /dev/null -w '%{http_code}\n' https://luts.miniserver.fun/up
```

Ожидается: HTTP перенаправляет на HTTPS; `/`, `/up` и Vite asset возвращают 200.

- [x] **Шаг 3: Сверить Git и резервную копию**

Ожидается: `HEAD == origin/main`; в рабочем дереве остаются только намеренно исключённые server/runtime-файлы; путь и checksum резервной копии зафиксированы в итоговом отчёте.
