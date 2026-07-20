<?php

namespace App\Services\LutTester;

use App\Models\ProductFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InspectCubeFile
{
    public function inspect(ProductFile $file): CubeInspectionResult
    {
        $disk = Storage::disk($file->disk);

        if ($file->disk !== (string) config('lut-tester.disk', 'private') || ! $disk->exists($file->path)) {
            throw new RuntimeException('CUBE file is not available on the private disk.');
        }

        $sizeBytes = $disk->size($file->path);
        $maxBytes = (int) config('lut-tester.max_cube_size_bytes', 20 * 1024 * 1024);

        if ($sizeBytes > $maxBytes) {
            throw new RuntimeException('CUBE file is larger than the configured limit.');
        }

        $stream = $disk->readStream($file->path);

        if ($stream === null) {
            throw new RuntimeException('CUBE file could not be opened.');
        }

        try {
            return $this->inspectStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    public function inspectStream($stream): CubeInspectionResult
    {
        $maxLineLength = max(1, (int) config('lut-tester.max_cube_line_length', 8_192));
        $maxChannelValue = (float) config('lut-tester.max_cube_absolute_channel_value', 64.0);
        $title = null;
        $domainMin = null;
        $domainMax = null;
        $size = null;
        $rows = 0;

        while (($rawLine = fgets($stream, $maxLineLength + 2)) !== false) {
            if (! str_ends_with($rawLine, "\n") && ! feof($stream) && strlen($rawLine) > $maxLineLength) {
                throw new RuntimeException('CUBE file contains an overlong line.');
            }

            if (str_contains($rawLine, "\0")) {
                throw new RuntimeException('CUBE file contains invalid NUL bytes.');
            }

            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $keyword = strtok($line, " \t");

            if ($keyword === 'LUT_1D_SIZE') {
                throw new RuntimeException('1D LUT files are not supported for testing.');
            }

            if ($keyword === 'TITLE') {
                $title = $this->parseTitle($line);

                continue;
            }

            if ($keyword === 'DOMAIN_MIN') {
                $domainMin = $this->parseRgbDirective($line, 'DOMAIN_MIN', $maxChannelValue);

                continue;
            }

            if ($keyword === 'DOMAIN_MAX') {
                $domainMax = $this->parseRgbDirective($line, 'DOMAIN_MAX', $maxChannelValue);

                continue;
            }

            if ($keyword === 'LUT_3D_SIZE') {
                if ($size !== null) {
                    throw new RuntimeException('CUBE file contains more than one LUT_3D_SIZE declaration.');
                }

                $size = $this->parseSize($line);

                continue;
            }

            if (preg_match('/^[A-Z_]+$/', $keyword) === 1) {
                throw new RuntimeException('CUBE file contains an unsupported directive.');
            }

            if ($size === null) {
                throw new RuntimeException('CUBE RGB data appeared before LUT_3D_SIZE.');
            }

            $this->parseRgbRow($line, $maxChannelValue);
            $rows++;

            if ($rows > ($size ** 3)) {
                throw new RuntimeException('CUBE file contains too many RGB rows.');
            }
        }

        if ($size === null) {
            throw new RuntimeException('CUBE file is missing LUT_3D_SIZE.');
        }

        $expectedRows = $size ** 3;

        if ($rows !== $expectedRows) {
            throw new RuntimeException('CUBE file contains an incorrect number of RGB rows.');
        }

        return new CubeInspectionResult(
            size: $size,
            rows: $rows,
            title: $title,
            domainMin: $domainMin,
            domainMax: $domainMax,
        );
    }

    private function parseTitle(string $line): string
    {
        $value = trim(substr($line, strlen('TITLE')));

        if ($value === '') {
            throw new RuntimeException('CUBE TITLE directive is malformed.');
        }

        return trim($value, "\"'");
    }

    /**
     * @return array<int, float>
     */
    private function parseRgbDirective(string $line, string $directive, float $maxChannelValue): array
    {
        $value = trim(substr($line, strlen($directive)));
        $tokens = preg_split('/\s+/', $value) ?: [];

        if (count($tokens) !== 3) {
            throw new RuntimeException($directive.' must contain exactly three values.');
        }

        return array_map(fn (string $token): float => $this->parseFiniteNumber($token, $maxChannelValue), $tokens);
    }

    private function parseSize(string $line): int
    {
        $value = trim(substr($line, strlen('LUT_3D_SIZE')));

        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new RuntimeException('LUT_3D_SIZE must be an integer.');
        }

        $size = (int) $value;

        if ($size < 2 || $size > 65) {
            throw new RuntimeException('LUT_3D_SIZE must be between 2 and 65.');
        }

        return $size;
    }

    private function parseRgbRow(string $line, float $maxChannelValue): void
    {
        $tokens = preg_split('/\s+/', $line) ?: [];

        if (count($tokens) !== 3) {
            throw new RuntimeException('CUBE RGB rows must contain exactly three values.');
        }

        foreach ($tokens as $token) {
            $this->parseFiniteNumber($token, $maxChannelValue);
        }
    }

    private function parseFiniteNumber(string $token, float $maxChannelValue): float
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $token) !== 1) {
            throw new RuntimeException('CUBE numeric value is malformed.');
        }

        $value = (float) $token;

        if (! is_finite($value)) {
            throw new RuntimeException('CUBE numeric value is not finite.');
        }

        if (abs($value) > $maxChannelValue) {
            throw new RuntimeException('CUBE numeric value exceeds the configured safe limit.');
        }

        return $value;
    }
}
