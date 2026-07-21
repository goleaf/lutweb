<?php

namespace App\ValueObjects;

use InvalidArgumentException;

final readonly class LutTransformParameters
{
    /**
     * @var array<string, array{label: string, group: string, minimum: int, maximum: int, default: int, display_scale: int, ui_step: int, unit: string}>
     */
    private const DEFINITIONS = [
        'intensity' => [
            'label' => 'Intensity',
            'group' => 'Basic',
            'minimum' => 0,
            'maximum' => 1000,
            'default' => 1000,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '%',
        ],
        'exposure' => [
            'label' => 'Exposure',
            'group' => 'Basic',
            'minimum' => -200,
            'maximum' => 200,
            'default' => 0,
            'display_scale' => 100,
            'ui_step' => 5,
            'unit' => 'EV',
        ],
        'contrast' => [
            'label' => 'Contrast',
            'group' => 'Basic',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'temperature' => [
            'label' => 'Temperature',
            'group' => 'Color',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'tint' => [
            'label' => 'Tint',
            'group' => 'Color',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'saturation' => [
            'label' => 'Saturation',
            'group' => 'Color',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'vibrance' => [
            'label' => 'Vibrance',
            'group' => 'Color',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'highlights' => [
            'label' => 'Highlights',
            'group' => 'Tone',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'shadows' => [
            'label' => 'Shadows',
            'group' => 'Tone',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'whites' => [
            'label' => 'Whites',
            'group' => 'Tone',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'blacks' => [
            'label' => 'Blacks',
            'group' => 'Tone',
            'minimum' => -1000,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'fade' => [
            'label' => 'Fade',
            'group' => 'Tone',
            'minimum' => 0,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '',
        ],
        'shadow_hue' => [
            'label' => 'Shadow Hue',
            'group' => 'Split Toning',
            'minimum' => 0,
            'maximum' => 3599,
            'default' => 2100,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => 'deg',
        ],
        'shadow_strength' => [
            'label' => 'Shadow Color Strength',
            'group' => 'Split Toning',
            'minimum' => 0,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '%',
        ],
        'highlight_hue' => [
            'label' => 'Highlight Hue',
            'group' => 'Split Toning',
            'minimum' => 0,
            'maximum' => 3599,
            'default' => 400,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => 'deg',
        ],
        'highlight_strength' => [
            'label' => 'Highlight Color Strength',
            'group' => 'Split Toning',
            'minimum' => 0,
            'maximum' => 1000,
            'default' => 0,
            'display_scale' => 10,
            'ui_step' => 10,
            'unit' => '%',
        ],
    ];

    /**
     * @param  array<string, int>  $values
     */
    private function __construct(private array $values) {}

    public static function neutral(): self
    {
        return new self(self::defaults());
    }

    /**
     * @param  array<mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        self::assertExactKeys($values);

        $canonical = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $value = $values[$key];

            if (! is_int($value)) {
                throw new InvalidArgumentException("The {$key} parameter must be an integer.");
            }

            if ($value < $definition['minimum'] || $value > $definition['maximum']) {
                throw new InvalidArgumentException("The {$key} parameter is outside the supported range.");
            }

            $canonical[$key] = $value;
        }

        return new self($canonical);
    }

    /**
     * @param  array<string, int>  $changes
     */
    public function withChanges(array $changes): self
    {
        return self::fromArray([
            ...$this->values,
            ...$changes,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function canonicalJson(): string
    {
        return json_encode($this->values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonicalJson());
    }

    public function equals(self $parameters): bool
    {
        return $this->values === $parameters->values;
    }

    /**
     * @return list<array{key: string, label: string, group: string, minimum: int, maximum: int, default: int, display_scale: int, ui_step: int, unit: string}>
     */
    public static function schema(): array
    {
        $schema = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $schema[] = [
                'key' => $key,
                ...$definition,
            ];
        }

        return $schema;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * @return array<string, int>
     */
    public static function defaults(): array
    {
        return array_map(
            fn (array $definition): int => $definition['default'],
            self::DEFINITIONS,
        );
    }

    public static function minimum(string $key): int
    {
        return self::definition($key)['minimum'];
    }

    public static function maximum(string $key): int
    {
        return self::definition($key)['maximum'];
    }

    public static function span(string $key): int
    {
        return self::maximum($key) - self::minimum($key);
    }

    public static function isHueKey(string $key): bool
    {
        return in_array($key, ['shadow_hue', 'highlight_hue'], true);
    }

    public function intensity(): int
    {
        return $this->values['intensity'];
    }

    public function exposure(): int
    {
        return $this->values['exposure'];
    }

    public function contrast(): int
    {
        return $this->values['contrast'];
    }

    public function temperature(): int
    {
        return $this->values['temperature'];
    }

    public function tint(): int
    {
        return $this->values['tint'];
    }

    public function saturation(): int
    {
        return $this->values['saturation'];
    }

    public function vibrance(): int
    {
        return $this->values['vibrance'];
    }

    public function highlights(): int
    {
        return $this->values['highlights'];
    }

    public function shadows(): int
    {
        return $this->values['shadows'];
    }

    public function whites(): int
    {
        return $this->values['whites'];
    }

    public function blacks(): int
    {
        return $this->values['blacks'];
    }

    public function fade(): int
    {
        return $this->values['fade'];
    }

    public function shadowHue(): int
    {
        return $this->values['shadow_hue'];
    }

    public function shadowStrength(): int
    {
        return $this->values['shadow_strength'];
    }

    public function highlightHue(): int
    {
        return $this->values['highlight_hue'];
    }

    public function highlightStrength(): int
    {
        return $this->values['highlight_strength'];
    }

    /**
     * @param  array<mixed>  $values
     */
    private static function assertExactKeys(array $values): void
    {
        $expected = self::keys();
        $actual = array_keys($values);
        $missing = array_values(array_diff($expected, $actual));
        $unknown = array_values(array_diff($actual, $expected));

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing LUT transform parameter keys: '.implode(', ', $missing).'.');
        }

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown LUT transform parameter keys: '.implode(', ', $unknown).'.');
        }
    }

    /**
     * @return array{label: string, group: string, minimum: int, maximum: int, default: int, display_scale: int, ui_step: int, unit: string}
     */
    private static function definition(string $key): array
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new InvalidArgumentException("Unknown LUT transform parameter key [{$key}].");
        }

        return self::DEFINITIONS[$key];
    }
}
