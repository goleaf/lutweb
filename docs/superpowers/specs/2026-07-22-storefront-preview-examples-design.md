# Примеры Before/After для preview-каталога

## Цель

Добавить каждому из 300 preview-LUT один активный и готовый пример Before/After, не создавая фиктивные версии продуктов, скачиваемые файлы, пользователей, заказы или платежи. После публикации `storefront-media:doctor` должен показывать ноль опубликованных товаров без Ready examples.

## Выбранный подход

Отдельный action `GenerateStorefrontPreviewExample` получает продукт и запись `StorefrontPreviewCatalog`. Он использует тот же оригинальный AI-исходник, который применялся для обложки: исходник становится стороной Before, а индивидуальный CUBE 17 и настроенный FFmpeg создают сторону After. Обе стороны проходят существующий `ApplyPreviewWatermark` и `GenerateStorefrontImageVariants`.

Альтернативы отклонены:

- Создание `ProductVersion` и `ProductFile` только ради существующего `ProcessProductExample` ложно сделало бы preview-товары похожими на готовые коммерческие пакеты.
- Использование одной обложки как After без независимой Before-стороны не дало бы полноценного сравнения.
- Общий пример на категорию нельзя корректно связать с каждым товаром через существующую модель `ProductExample`.

## Данные и идемпотентность

- Для каждого preview-товара создаётся одна запись с устойчивым заголовком `Original vs {Product Name}`.
- `before_path` и `after_path` остаются пустыми: публичная витрина использует только `StorefrontImageVariant`, без legacy direct-path режима.
- Источник, SHA-256, размеры и права фиксируются так же, как у preview-cover.
- `processed_product_version_id` и `processed_product_file_id` остаются `null`.
- Fingerprint включает SKU, SHA-256 источника, hash параметров LUT, pipeline version, responsive widths, JPEG/WebP quality, watermark-настройки, FFmpeg interpolation и CUBE size.
- Повторный запуск возвращает существующий Ready example, если fingerprint и все ожидаемые публичные варианты совпадают.

## Генерация файлов

На один пример создаются четыре ширины `480`, `768`, `1200`, `1600`, два формата JPEG/WebP и две роли Before/After — всего 16 вариантов. Временные исходник, CUBE, graded и watermarked-файлы размещаются только в приватном work-каталоге и удаляются в `finally`.

При ошибке новые варианты удаляются, пример переводится в `failed`, пользователю сохраняется безопасное сообщение, а технический класс исключения записывается в журнал.

## Сидирование

`StorefrontPreviewMediaSeeder` после генерации cover вызывает генерацию example для той же записи каталога. Progress выводится раздельно для covers и examples. Product-only seeder продолжает создавать только каталог и не создаёт media/examples.

## Проверка

- Pest RED/GREEN проверяет Before/After, 16 физических вариантов, подтверждённые права, отсутствие product version/file и стабильные ID при повторном запуске.
- Полный изолированный запуск проверяет 300 examples и 4800 example variants без дубликатов.
- Production получает резервную копию SQLite, сид запускается от `www` с временной остановкой фоновых сервисов.
- Doctor, HTTP и Playwright проверяют ноль missing Ready examples, работающий слайдер и отсутствие console errors/overflow на desktop/mobile.

## Границы

Коммерческие `.cube`/ZIP-файлы, checkout, PayPal и реальные заказы не входят в эту задачу. Примеры демонстрируют цветовой результат, но не меняют доступность покупки.
