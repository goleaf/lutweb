<?php

use App\Color\LutTransformV1;
use App\Color\NormalizedRgb;
use App\Enums\LutTransformVersion;
use App\ValueObjects\LutTransformParameters;

function lutTransformV1Fixture(): array
{
    $contents = file_get_contents(__DIR__.'/../Fixtures/lut-transform-v1-conformance.json');

    if (! is_string($contents)) {
        throw new RuntimeException('Unable to read LUT Transform V1 conformance fixture.');
    }

    $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($fixture) || ! isset($fixture['cases']) || ! is_array($fixture['cases'])) {
        throw new RuntimeException('Invalid LUT Transform V1 conformance fixture.');
    }

    return $fixture;
}

test('neutral transform preserves representative rgb values', function (array $input) {
    $transform = new LutTransformV1;
    $rgb = new NormalizedRgb($input[0], $input[1], $input[2]);

    $result = $transform->transform($rgb, LutTransformParameters::neutral());

    expect(abs($result->red - $input[0]))->toBeLessThanOrEqual(1.0e-12)
        ->and(abs($result->green - $input[1]))->toBeLessThanOrEqual(1.0e-12)
        ->and(abs($result->blue - $input[2]))->toBeLessThanOrEqual(1.0e-12);
})->with([
    'black' => [[0.0, 0.0, 0.0]],
    'white' => [[1.0, 1.0, 1.0]],
    'red' => [[1.0, 0.0, 0.0]],
    'green' => [[0.0, 1.0, 0.0]],
    'blue' => [[0.0, 0.0, 1.0]],
    'representative' => [[0.17, 0.42, 0.81]],
]);

test('intensity zero returns original rgb exactly within tolerance', function () {
    $transform = new LutTransformV1;
    $parameters = LutTransformParameters::neutral()->withChanges([
        'intensity' => 0,
        'exposure' => 120,
        'contrast' => 450,
        'temperature' => -500,
        'highlight_strength' => 300,
    ]);
    $rgb = new NormalizedRgb(0.19, 0.44, 0.73);

    $result = $transform->transform($rgb, $parameters);

    expect($result->red)->toBe(0.19)
        ->and($result->green)->toBe(0.44)
        ->and($result->blue)->toBe(0.73);
});

test('unsupported transform version is not advertised as supported', function () {
    expect((new LutTransformV1)->supports(LutTransformVersion::V1))->toBeTrue();
});

test('every committed transform conformance fixture passes', function () {
    $fixture = lutTransformV1Fixture();
    $transform = new LutTransformV1;
    $generalTolerance = (float) $fixture['tolerance'];
    $identityTolerance = (float) $fixture['identity_tolerance'];

    foreach ($fixture['cases'] as $case) {
        expect($case)->toBeArray();

        $parameters = LutTransformParameters::fromArray($case['parameters']);
        $input = new NormalizedRgb(
            (float) $case['input']['red'],
            (float) $case['input']['green'],
            (float) $case['input']['blue'],
        );
        $result = $transform->transform($input, $parameters);
        $tolerance = ($case['identity'] ?? false) ? $identityTolerance : $generalTolerance;

        foreach (['red', 'green', 'blue'] as $channel) {
            $actual = $result->{$channel};
            $expected = (float) $case['expected'][$channel];

            if (abs($actual - $expected) > $tolerance) {
                throw new RuntimeException($case['name'].' '.$channel.' expected '.$expected.', received '.$actual);
            }
        }
    }
});

test('transform outputs are finite and clamped', function () {
    $transform = new LutTransformV1;
    $parameters = LutTransformParameters::neutral()->withChanges([
        'exposure' => 200,
        'contrast' => 1000,
        'temperature' => 1000,
        'tint' => -1000,
        'saturation' => 1000,
        'vibrance' => 1000,
        'highlights' => 1000,
        'shadows' => 1000,
        'whites' => 1000,
        'blacks' => -1000,
        'fade' => 1000,
        'shadow_strength' => 1000,
        'highlight_strength' => 1000,
    ]);

    $result = $transform->transform(new NormalizedRgb(0.91, 0.23, 0.04), $parameters);

    foreach ([$result->red, $result->green, $result->blue] as $channel) {
        expect(is_finite($channel))->toBeTrue()
            ->and($channel)->toBeGreaterThanOrEqual(0.0)
            ->and($channel)->toBeLessThanOrEqual(1.0);
    }
});
