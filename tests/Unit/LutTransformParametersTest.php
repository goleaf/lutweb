<?php

use App\ValueObjects\LutTransformParameters;

test('neutral parameters contain every required key in deterministic order', function () {
    $parameters = LutTransformParameters::neutral();

    expect(array_keys($parameters->toArray()))->toBe([
        'intensity',
        'exposure',
        'contrast',
        'temperature',
        'tint',
        'saturation',
        'vibrance',
        'highlights',
        'shadows',
        'whites',
        'blacks',
        'fade',
        'shadow_hue',
        'shadow_strength',
        'highlight_hue',
        'highlight_strength',
    ]);

    expect($parameters->intensity())->toBe(1000)
        ->and($parameters->exposure())->toBe(0)
        ->and($parameters->shadowHue())->toBe(2100)
        ->and($parameters->highlightHue())->toBe(400);
});

test('canonical JSON and hash are deterministic', function () {
    $first = LutTransformParameters::neutral();
    $second = LutTransformParameters::fromArray(array_reverse($first->toArray(), preserve_keys: true));

    expect($first->canonicalJson())->toBe($second->canonicalJson())
        ->and($first->hash())->toBe($second->hash())
        ->and($first->canonicalJson())->toBe('{"intensity":1000,"exposure":0,"contrast":0,"temperature":0,"tint":0,"saturation":0,"vibrance":0,"highlights":0,"shadows":0,"whites":0,"blacks":0,"fade":0,"shadow_hue":2100,"shadow_strength":0,"highlight_hue":400,"highlight_strength":0}');
});

test('different parameters create a different hash', function () {
    expect(LutTransformParameters::neutral()->hash())
        ->not->toBe(LutTransformParameters::neutral()->withChanges(['contrast' => 100])->hash());
});

test('unknown and missing keys are rejected', function () {
    expect(fn () => LutTransformParameters::fromArray([
        ...LutTransformParameters::neutral()->toArray(),
        'unknown' => 1,
    ]))->toThrow(InvalidArgumentException::class);

    $missing = LutTransformParameters::neutral()->toArray();
    unset($missing['contrast']);

    expect(fn () => LutTransformParameters::fromArray($missing))
        ->toThrow(InvalidArgumentException::class);
});

test('non integer parameter values are rejected', function (mixed $value) {
    $parameters = LutTransformParameters::neutral()->toArray();
    $parameters['contrast'] = $value;

    expect(fn () => LutTransformParameters::fromArray($parameters))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'string' => ['1'],
    'boolean' => [true],
    'null' => [null],
    'float' => [1.0],
]);

test('global ranges are enforced', function (string $key, int $value) {
    $parameters = LutTransformParameters::neutral()->toArray();
    $parameters[$key] = $value;

    expect(fn () => LutTransformParameters::fromArray($parameters))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'intensity below' => ['intensity', -1],
    'intensity above' => ['intensity', 1001],
    'exposure below' => ['exposure', -201],
    'exposure above' => ['exposure', 201],
    'hue above' => ['shadow_hue', 3600],
]);

test('range boundaries are accepted', function () {
    $parameters = LutTransformParameters::neutral()->withChanges([
        'intensity' => 0,
        'exposure' => -200,
        'shadow_hue' => 0,
    ]);

    expect($parameters->intensity())->toBe(0)
        ->and($parameters->exposure())->toBe(-200)
        ->and($parameters->shadowHue())->toBe(0);

    $parameters = $parameters->withChanges([
        'intensity' => 1000,
        'exposure' => 200,
        'shadow_hue' => 3599,
    ]);

    expect($parameters->intensity())->toBe(1000)
        ->and($parameters->exposure())->toBe(200)
        ->and($parameters->shadowHue())->toBe(3599);
});

test('withChanges is immutable and equality compares canonical values', function () {
    $original = LutTransformParameters::neutral();
    $changed = $original->withChanges(['contrast' => 125]);

    expect($original->contrast())->toBe(0)
        ->and($changed->contrast())->toBe(125)
        ->and($original->equals($changed))->toBeFalse()
        ->and($changed->equals(LutTransformParameters::fromArray($changed->toArray())))->toBeTrue();
});
