<?php

namespace App\Services\CustomLutBuilds;

use App\Color\LutTransformV1;
use App\Color\NormalizedRgb;
use App\ValueObjects\LutTransformParameters;
use RuntimeException;

class MeasurePreviewParity
{
    private const PREVIEW_SIZE = 33;

    public function __construct(private readonly LutTransformV1 $transform) {}

    public function handle(LutTransformParameters $parameters): PreviewParityMetrics
    {
        $this->assertLatticeParity($parameters);

        $errors = [];

        foreach ($this->samplePoints((int) config('custom-lut-builds.parity_sample_count', 4096)) as $point) {
            $preview = $this->samplePreview($point, $parameters);
            $direct = $this->transform->transform($point, $parameters);

            $errors[] = abs($preview->red - $direct->red) * 255;
            $errors[] = abs($preview->green - $direct->green) * 255;
            $errors[] = abs($preview->blue - $direct->blue) * 255;
        }

        sort($errors);

        $metrics = new PreviewParityMetrics(
            meanMillionths: $this->scale(array_sum($errors) / max(1, count($errors))),
            p95Millionths: $this->scale($this->percentile($errors, 95)),
            p99Millionths: $this->scale($this->percentile($errors, 99)),
            maxMillionths: $this->scale((float) ($errors[array_key_last($errors)] ?? 0.0)),
        );

        $thresholds = config('custom-lut-builds.parity_thresholds', []);

        if (
            $metrics->meanMillionths > (int) ($thresholds['between_mean_millionths'] ?? 2_750_000)
            || $metrics->p95Millionths > (int) ($thresholds['between_p95_millionths'] ?? 7_500_000)
            || $metrics->p99Millionths > (int) ($thresholds['between_p99_millionths'] ?? 11_500_000)
            || $metrics->maxMillionths > (int) ($thresholds['between_max_millionths'] ?? 17_500_000)
        ) {
            throw new RuntimeException('Preview parity exceeded configured thresholds.');
        }

        return $metrics;
    }

    private function assertLatticeParity(LutTransformParameters $parameters): void
    {
        $maxErrorMillionths = 0;
        $lastIndex = self::PREVIEW_SIZE - 1;

        for ($blueIndex = 0; $blueIndex < self::PREVIEW_SIZE; $blueIndex++) {
            for ($greenIndex = 0; $greenIndex < self::PREVIEW_SIZE; $greenIndex++) {
                for ($redIndex = 0; $redIndex < self::PREVIEW_SIZE; $redIndex++) {
                    $source = new NormalizedRgb($redIndex / $lastIndex, $greenIndex / $lastIndex, $blueIndex / $lastIndex);
                    $preview = $this->samplePreview($source, $parameters);
                    $direct = $this->transform->transform($source, $parameters);
                    $maxErrorMillionths = max(
                        $maxErrorMillionths,
                        $this->scale(abs($preview->red - $direct->red) * 255),
                        $this->scale(abs($preview->green - $direct->green) * 255),
                        $this->scale(abs($preview->blue - $direct->blue) * 255),
                    );
                }
            }
        }

        if ($maxErrorMillionths > (int) config('custom-lut-builds.parity_thresholds.lattice_max_millionths', 500_000)) {
            throw new RuntimeException('Preview lattice parity exceeded the RGBA8 quantization threshold.');
        }
    }

    private function samplePreview(NormalizedRgb $source, LutTransformParameters $parameters): NormalizedRgb
    {
        $size = self::PREVIEW_SIZE;
        $scaledRed = $source->red * ($size - 1);
        $scaledGreen = $source->green * ($size - 1);
        $scaledBlue = $source->blue * ($size - 1);
        $red0 = (int) floor($scaledRed);
        $green0 = (int) floor($scaledGreen);
        $blue0 = (int) floor($scaledBlue);
        $red1 = min($size - 1, $red0 + 1);
        $green1 = min($size - 1, $green0 + 1);
        $blue1 = min($size - 1, $blue0 + 1);
        $redT = $scaledRed - $red0;
        $greenT = $scaledGreen - $green0;
        $blueT = $scaledBlue - $blue0;

        $c000 = $this->previewNode($red0, $green0, $blue0, $parameters);
        $c100 = $this->previewNode($red1, $green0, $blue0, $parameters);
        $c010 = $this->previewNode($red0, $green1, $blue0, $parameters);
        $c110 = $this->previewNode($red1, $green1, $blue0, $parameters);
        $c001 = $this->previewNode($red0, $green0, $blue1, $parameters);
        $c101 = $this->previewNode($red1, $green0, $blue1, $parameters);
        $c011 = $this->previewNode($red0, $green1, $blue1, $parameters);
        $c111 = $this->previewNode($red1, $green1, $blue1, $parameters);

        $sample = new NormalizedRgb(
            $this->trilinear($c000->red, $c100->red, $c010->red, $c110->red, $c001->red, $c101->red, $c011->red, $c111->red, $redT, $greenT, $blueT),
            $this->trilinear($c000->green, $c100->green, $c010->green, $c110->green, $c001->green, $c101->green, $c011->green, $c111->green, $redT, $greenT, $blueT),
            $this->trilinear($c000->blue, $c100->blue, $c010->blue, $c110->blue, $c001->blue, $c101->blue, $c011->blue, $c111->blue, $redT, $greenT, $blueT),
        );

        $intensity = $parameters->intensity() / 1000;

        return new NormalizedRgb(
            $source->red * (1 - $intensity) + $sample->red * $intensity,
            $source->green * (1 - $intensity) + $sample->green * $intensity,
            $source->blue * (1 - $intensity) + $sample->blue * $intensity,
        );
    }

    private function previewNode(int $redIndex, int $greenIndex, int $blueIndex, LutTransformParameters $parameters): NormalizedRgb
    {
        $size = self::PREVIEW_SIZE;
        $parametersWithoutIntensity = $parameters->withChanges(['intensity' => 1000]);
        $rgb = $this->transform->transform(new NormalizedRgb(
            $redIndex / ($size - 1),
            $greenIndex / ($size - 1),
            $blueIndex / ($size - 1),
        ), $parametersWithoutIntensity);

        return new NormalizedRgb(
            round($rgb->red * 255) / 255,
            round($rgb->green * 255) / 255,
            round($rgb->blue * 255) / 255,
        );
    }

    private function trilinear(
        float $c000,
        float $c100,
        float $c010,
        float $c110,
        float $c001,
        float $c101,
        float $c011,
        float $c111,
        float $redT,
        float $greenT,
        float $blueT,
    ): float {
        $c00 = $c000 * (1 - $redT) + $c100 * $redT;
        $c10 = $c010 * (1 - $redT) + $c110 * $redT;
        $c01 = $c001 * (1 - $redT) + $c101 * $redT;
        $c11 = $c011 * (1 - $redT) + $c111 * $redT;
        $c0 = $c00 * (1 - $greenT) + $c10 * $greenT;
        $c1 = $c01 * (1 - $greenT) + $c11 * $greenT;

        return min(1.0, max(0.0, $c0 * (1 - $blueT) + $c1 * $blueT));
    }

    /**
     * @return iterable<NormalizedRgb>
     */
    private function samplePoints(int $minimumCount): iterable
    {
        $points = [
            new NormalizedRgb(0, 0, 0),
            new NormalizedRgb(1, 1, 1),
            new NormalizedRgb(1, 0, 0),
            new NormalizedRgb(0, 1, 0),
            new NormalizedRgb(0, 0, 1),
            new NormalizedRgb(0.5, 0.5, 0.5),
        ];

        for ($index = 0; $index <= 32; $index++) {
            $value = $index / 32;
            $points[] = new NormalizedRgb($value, 0, 0);
            $points[] = new NormalizedRgb(0, $value, 0);
            $points[] = new NormalizedRgb(0, 0, $value);
            $points[] = new NormalizedRgb($value, $value, $value);
        }

        foreach ($points as $point) {
            yield $point;
        }

        for ($index = count($points); $index < $minimumCount; $index++) {
            $hash = hash('sha256', 'custom-lut-preview-parity|'.$index, true);
            $red = $this->unsignedShortFromBytes(substr($hash, 0, 2)) / 65535;
            $green = $this->unsignedShortFromBytes(substr($hash, 2, 2)) / 65535;
            $blue = $this->unsignedShortFromBytes(substr($hash, 4, 2)) / 65535;

            yield new NormalizedRgb($red, $green, $blue);
        }
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, int $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        $index = (int) ceil(($percentile / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }

    private function scale(float $errorInEightBitLevels): int
    {
        return (int) round($errorInEightBitLevels * 1_000_000);
    }

    private function unsignedShortFromBytes(string $bytes): int
    {
        $value = unpack('nvalue', $bytes);

        if (! is_array($value)) {
            throw new RuntimeException('Unable to derive deterministic parity sample.');
        }

        return (int) $value['value'];
    }
}
