# Storefront Catalog Polish Implementation Plan

> **Для agentic workers:** ОБЯЗАТЕЛЬНЫЙ SUB-SKILL: использовать `superpowers:executing-plans` для последовательного выполнения этого плана.

**Цель:** Исправить Shop header, распределить featured LUT по подходящим категориям и безопасно опубликовать каталог из 300 разных LUT с готовыми responsive-обложками.

**Архитектура:** Детерминированный PHP-каталог отделяет описание продуктов и цветовых профилей от Eloquent seeder. Лёгкий product seeder создаёт и связывает продукты; отдельный media seeder использует существующие `WriteCubeFile`, FFmpeg `lut3d` и `GenerateStorefrontImageVariants`. Общий Vue-компонент заголовка исправляется минимально, а Shop получает компактные метрики.

**Технологии:** PHP 8.5, Laravel 13, Pest 4, Inertia 3, Vue 3, Tailwind CSS 4, FFmpeg 6.1, Playwright CLI, SQLite.

## Глобальные ограничения

- Не добавлять зависимости и новые верхнеуровневые каталоги.
- Не коммитить `.env`, production SQLite, runtime storage, `.user.ini` или `.well-known`.
- Не создавать фиктивные users/orders/payments/product files.
- Для PHP соблюдать TDD и запускать `vendor/bin/pint --dirty --format agent`.
- Production изменять только после проверенной резервной копии.

## Текущий статус

- [x] Причина склеенного Shop header воспроизведена в реальном браузере.
- [x] Существующие featured LUT и production-данные проаудированы.
- [x] Подтверждена безопасная стратегия каталога и изображений.
- [x] Реализовать и проверить UI.
- [x] Реализовать и проверить featured-категории.
- [x] Реализовать и проверить каталог 30 × 10.
- [x] Создать исходники и media pipeline.
- [x] Опубликовать, просидировать и проверить production.

### Задача 1: UI Shop — RED/GREEN

**Файлы:**
- Изменить: `resources/js/components/ui/SectionHeading.vue`
- Изменить: `resources/js/pages/Shop/Index.vue`
- Проверить: `tests/Feature/PublicStorefrontTest.php`

- [x] Воспроизвести в Playwright склеенные eyebrow/title и чрезмерный размер заголовка.
- [x] Зафиксировать исходные размеры и положение элементов до исправления.
- [x] Заменить inline-flex заголовка на вертикальный block/grid, установить Shop `size="section"`, сократить padding и добавить метрики с `package`/`sliders`.
- [x] Запустить узкий тест, `npm run types:check`, `npm run lint:check`, `npm run build`.
- [x] Проверить desktop/mobile через Playwright CLI: размер h1 ≤ 24 px, eyebrow выше h1, overflow отсутствует.

### Задача 2: Featured-категории — RED/GREEN

**Файлы:**
- Изменить: `tests/Feature/StorefrontPreviewSeederTest.php`
- Изменить: `database/seeders/StorefrontPreviewSeeder.php`

- [x] Сначала добавить проверки Alpine и Golden City по утверждённой карте категорий.
- [x] Запустить тест и увидеть падение на отсутствующих связях.
- [x] Идемпотентно добавить точные связи через `sync` из детерминированного каталога.
- [x] Повторно запустить seeder test и подтвердить стабильные ID/связи.

### Задача 3: Детерминированный каталог 30 × 10 — RED/GREEN

**Файлы:**
- Создать: `app/Support/Storefront/StorefrontPreviewCatalog.php`
- Изменить: `database/seeders/StorefrontPreviewSeeder.php`
- Изменить: `tests/Feature/StorefrontPreviewSeederTest.php`

- [x] Добавить тесты: 300 уникальных SKU/slug/title, по 30 основных продуктов в каждой обязательной категории, Published status, отсутствие операционных данных и повторная идемпотентность.
- [x] Запустить тест и подтвердить падение при текущих шести продуктах.
- [x] Реализовать 30 именованных профилей, десять категорий и category-specific данные; сохранить шесть текущих Travel slug.
- [x] Перевести seeder на `updateOrCreate` и точную синхронизацию основной категории с дополнительными featured-связями.
- [x] Повторно запустить узкий тест дважды.

### Задача 4: Media pipeline — RED/GREEN

**Файлы:**
- Создать: `app/Actions/Storefront/GenerateStorefrontPreviewCover.php`
- Создать: `database/seeders/StorefrontPreviewMediaSeeder.php`
- Создать/изменить: `tests/Feature/StorefrontPreviewMediaSeederTest.php`
- Добавить: `database/seeders/assets/storefront-preview/*.jpg`

- [x] Добавить тест профилей/fingerprint и одного реального cover path на изолированном storage.
- [x] Запустить тест и подтвердить ожидаемое падение.
- [x] Сгенерировать десять оригинальных source-изображений и визуально проверить их.
- [x] Реализовать action: временный CUBE 17, FFmpeg `format=rgb24,lut3d=...:interp=tetrahedral`, media fingerprint, очистка устаревших variants, responsive generation.
- [x] Реализовать идемпотентный media seeder для всех 300 продуктов.
- [x] Запустить media test и повторный запуск для проверки отсутствия дубликатов.

### Задача 5: Проверки ветки

- [x] Запустить `vendor/bin/pint --dirty --format agent`.
- [x] Запустить узкие тесты, затем `php artisan test --compact` с временным test APP_KEY.
- [x] Запустить `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`.
- [x] Запустить `npm run test:lut-transform`, `npm run lint:check`, `npm run types:check`, `npm run format:check`, `npm run build`.
- [x] Проверить `git diff --check`, отсутствие секретов и runtime-файлов.

### Задача 6: Production backup, seed и live QA

- [x] Fast-forward/merge проверенную ветку в `main` без затрагивания server-managed файлов.
- [x] Создать `/www/backup/lutweb/database/database-before-full-catalog-YYYYMMDD_HHMMSS.sqlite`, проверить integrity и SHA-256.
- [x] Остановить workers/scheduler, запустить product seeder и media seeder от `www`, затем гарантированно вернуть службы в active state.
- [x] Повторно запустить seeders и подтвердить идемпотентность.
- [x] Проверить 300 основных продуктов, category counts 30–50, 300 Ready covers, responsive variants и нулевые users/orders/payments/product files.
- [x] Выполнить production build/optimize, doctor-команды и HTTPS health checks.
- [x] Через Playwright CLI проверить `/shop` и `/luts/travel` на desktop/mobile, console errors и overflow.
- [x] Закоммитить проверенные исходники/assets/docs, отправить `main` в `origin` и подтвердить состояние удалённой ветки через GitHub-коннектор.
