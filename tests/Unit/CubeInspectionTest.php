<?php

use App\Services\LutTester\InspectCubeFile;
use Tests\TestCase;

uses(TestCase::class);

function inspectCubeString(string $contents)
{
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $contents);
    rewind($stream);

    try {
        return app(InspectCubeFile::class)->inspectStream($stream);
    } finally {
        fclose($stream);
    }
}

function cubeInspectionIdentityCube(): string
{
    return <<<'CUBE'
TITLE "Identity"
LUT_3D_SIZE 2
0 0 0
0 0 1
0 1 0
0 1 1
1 0 0
1 0 1
1 1 0
1 1 1
CUBE;
}

test('a valid small identity 3D CUBE passes', function () {
    $result = inspectCubeString(cubeInspectionIdentityCube());

    expect($result->size)->toBe(2)
        ->and($result->rows)->toBe(8)
        ->and($result->title)->toBe('Identity');
});

test('comments and blank lines are accepted', function () {
    $cube = "# comment\n\n".cubeInspectionIdentityCube()."\n# trailing comment\n";

    expect(inspectCubeString($cube)->rows)->toBe(8);
});

test('invalid CUBE inputs are rejected', function (string $contents) {
    inspectCubeString($contents);
})->throws(RuntimeException::class)->with([
    '1D LUT' => ["LUT_1D_SIZE 2\n0 0 0\n1 1 1\n"],
    'missing size' => ["0 0 0\n"],
    'invalid size' => ["LUT_3D_SIZE 66\n0 0 0\n"],
    'incorrect row count' => ["LUT_3D_SIZE 2\n0 0 0\n"],
    'NaN' => ["LUT_3D_SIZE 2\nNaN 0 0\n"],
    'Infinity' => ["LUT_3D_SIZE 2\nInfinity 0 0\n"],
    'malformed RGB row' => ["LUT_3D_SIZE 2\n0 0\n"],
    'NUL byte' => ["LUT_3D_SIZE 2\n0 0 0\0\n"],
    'unexpected extra rows' => [cubeInspectionIdentityCube()."\n1 1 1\n"],
]);

test('overlong lines are rejected without needing one giant in-memory parse', function () {
    config(['lut-tester.max_cube_line_length' => 12]);

    inspectCubeString("LUT_3D_SIZE 2\n".str_repeat('1', 40)."\n");
})->throws(RuntimeException::class);
