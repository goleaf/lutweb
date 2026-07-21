<?php

namespace App\Services\CustomLutBuilds;

use App\Color\CubeSize;
use App\Color\LutTransformV1;
use App\Color\NormalizedRgb;
use App\ValueObjects\LutTransformParameters;
use RuntimeException;

class ValidateGeneratedCube
{
    public function __construct(private readonly LutTransformV1 $transform) {}

    public function handle(string $path, CubeSize $expectedSize, LutTransformParameters $parameters): void
    {
        if (! is_file($path) || filesize($path) === false || filesize($path) <= 0) {
            throw new RuntimeException('Generated CUBE file is missing.');
        }

        $prefix = file_get_contents($path, false, null, 0, 3);

        if ($prefix === "\xEF\xBB\xBF") {
            throw new RuntimeException('Generated CUBE file contains a UTF-8 BOM.');
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to open generated CUBE file.');
        }

        $size = null;
        $domainMin = null;
        $domainMax = null;
        $title = null;
        $dataRows = 0;
        $expectedRows = $expectedSize->rows();
        $lineNumber = 0;

        try {
            while (($line = fgets($stream)) !== false) {
                $lineNumber++;

                if (str_contains($line, "\0")) {
                    throw new RuntimeException('Generated CUBE file contains NUL bytes.');
                }

                if (str_ends_with($line, " \n") || str_ends_with($line, " \r\n")) {
                    throw new RuntimeException('Generated CUBE file contains trailing spaces.');
                }

                $trimmed = trim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                if (str_starts_with($trimmed, 'TITLE ')) {
                    if ($title !== null || ! preg_match('/^TITLE "[-a-z0-9]+"$/', $trimmed)) {
                        throw new RuntimeException('Generated CUBE file contains an unsafe TITLE.');
                    }

                    $title = $trimmed;

                    continue;
                }

                if (str_starts_with($trimmed, 'LUT_1D_SIZE')) {
                    throw new RuntimeException('Generated CUBE file contains a 1D LUT directive.');
                }

                if (str_starts_with($trimmed, 'LUT_3D_SIZE ')) {
                    if ($size !== null) {
                        throw new RuntimeException('Generated CUBE file contains multiple LUT_3D_SIZE directives.');
                    }

                    $value = (int) trim(substr($trimmed, strlen('LUT_3D_SIZE ')));

                    if ($value !== $expectedSize->value) {
                        throw new RuntimeException('Generated CUBE file has the wrong LUT size.');
                    }

                    $size = $value;

                    continue;
                }

                if (str_starts_with($trimmed, 'DOMAIN_MIN ')) {
                    $domainMin = $this->parseTriple(substr($trimmed, strlen('DOMAIN_MIN ')));
                    $this->assertTriple($domainMin, 0.0, 'DOMAIN_MIN');

                    continue;
                }

                if (str_starts_with($trimmed, 'DOMAIN_MAX ')) {
                    $domainMax = $this->parseTriple(substr($trimmed, strlen('DOMAIN_MAX ')));
                    $this->assertTriple($domainMax, 1.0, 'DOMAIN_MAX');

                    continue;
                }

                if ($size === null) {
                    throw new RuntimeException('Generated CUBE data appeared before LUT_3D_SIZE.');
                }

                if ($dataRows >= $expectedRows) {
                    throw new RuntimeException('Generated CUBE file contains unexpected extra data.');
                }

                $triple = $this->parseTriple($trimmed);
                $this->assertOutputRange($triple);
                $this->assertRowMatchesTransform($triple, $dataRows, $expectedSize, $parameters);
                $dataRows++;
            }
        } finally {
            fclose($stream);
        }

        if ($title === null || $domainMin === null || $domainMax === null || $size === null) {
            throw new RuntimeException('Generated CUBE file is missing required directives.');
        }

        if ($dataRows !== $expectedRows) {
            throw new RuntimeException('Generated CUBE file contains the wrong number of RGB rows.');
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function parseTriple(string $value): array
    {
        $parts = preg_split('/\s+/', trim($value));

        if ($parts === false || count($parts) !== 3) {
            throw new RuntimeException('Generated CUBE row must contain exactly three numeric values.');
        }

        return array_map(function (string $part): float {
            if (! preg_match('/^-?(?:0|[1-9][0-9]*)\.[0-9]+$/', $part)) {
                throw new RuntimeException('Generated CUBE value must use fixed decimal notation.');
            }

            $value = (float) $part;

            if (! is_finite($value)) {
                throw new RuntimeException('Generated CUBE value must be finite.');
            }

            return $value;
        }, $parts);
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $triple
     */
    private function assertTriple(array $triple, float $expected, string $label): void
    {
        foreach ($triple as $value) {
            if (abs($value - $expected) > 10 ** -((int) config('custom-lut-builds.cube_precision', 9))) {
                throw new RuntimeException("Generated CUBE {$label} is incorrect.");
            }
        }
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $triple
     */
    private function assertOutputRange(array $triple): void
    {
        foreach ($triple as $value) {
            if ($value < 0.0 || $value > 1.0) {
                throw new RuntimeException('Generated CUBE output value is outside 0 through 1.');
            }
        }
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $triple
     */
    private function assertRowMatchesTransform(array $triple, int $rowIndex, CubeSize $size, LutTransformParameters $parameters): void
    {
        $sizeSquared = $size->value * $size->value;
        $blueIndex = intdiv($rowIndex, $sizeSquared);
        $greenIndex = intdiv($rowIndex % $sizeSquared, $size->value);
        $redIndex = $rowIndex % $size->value;
        $lastIndex = $size->value - 1;
        $expected = $this->transform->transform(
            new NormalizedRgb($redIndex / $lastIndex, $greenIndex / $lastIndex, $blueIndex / $lastIndex),
            $parameters,
        );
        $tolerance = 0.5 * (10 ** -((int) config('custom-lut-builds.cube_precision', 9))) + 1.0e-12;

        if (
            abs($triple[0] - $expected->red) > $tolerance
            || abs($triple[1] - $expected->green) > $tolerance
            || abs($triple[2] - $expected->blue) > $tolerance
        ) {
            throw new RuntimeException('Generated CUBE row does not match the Transform V1 sample.');
        }
    }
}
