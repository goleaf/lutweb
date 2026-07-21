<?php

namespace App\Color;

use App\Enums\LutTransformVersion;
use App\ValueObjects\LutTransformParameters;

class LutTransformV1
{
    private const LUMA_RED = 0.2126;

    private const LUMA_GREEN = 0.7152;

    private const LUMA_BLUE = 0.0722;

    public function transform(NormalizedRgb $input, LutTransformParameters $parameters): NormalizedRgb
    {
        $originalRed = $input->red;
        $originalGreen = $input->green;
        $originalBlue = $input->blue;

        $red = $originalRed;
        $green = $originalGreen;
        $blue = $originalBlue;

        $exposureGain = 2 ** ($parameters->exposure() / 100);
        $red *= $exposureGain;
        $green *= $exposureGain;
        $blue *= $exposureGain;

        $temperatureValue = $parameters->temperature() / 1000;
        $tintValue = $parameters->tint() / 1000;
        $redGain = max(0.1, 1 + 0.18 * $temperatureValue + 0.03 * $tintValue);
        $greenGain = max(0.1, 1 - 0.1 * $tintValue);
        $blueGain = max(0.1, 1 - 0.18 * $temperatureValue + 0.03 * $tintValue);
        $red *= $redGain;
        $green *= $greenGain;
        $blue *= $blueGain;

        $contrastGain = 2 ** ($parameters->contrast() / 1000);
        $red = ($red - 0.5) * $contrastGain + 0.5;
        $green = ($green - 0.5) * $contrastGain + 0.5;
        $blue = ($blue - 0.5) * $contrastGain + 0.5;

        $luma = $this->luminance($red, $green, $blue);
        $shadowMask = 1 - $this->smoothstep(0.1, 0.6, $luma);
        $highlightMask = $this->smoothstep(0.4, 0.9, $luma);
        $blackMask = 1 - $this->smoothstep(0, 0.25, $luma);
        $whiteMask = $this->smoothstep(0.75, 1, $luma);
        $toneOffset =
            ($parameters->shadows() / 1000) * 0.22 * $shadowMask
            + ($parameters->highlights() / 1000) * 0.22 * $highlightMask
            + ($parameters->blacks() / 1000) * 0.18 * $blackMask
            + ($parameters->whites() / 1000) * 0.18 * $whiteMask;
        $red += $toneOffset;
        $green += $toneOffset;
        $blue += $toneOffset;

        $fadeValue = $parameters->fade() / 1000;
        $red = $red * (1 - 0.12 * $fadeValue) + 0.06 * $fadeValue;
        $green = $green * (1 - 0.12 * $fadeValue) + 0.06 * $fadeValue;
        $blue = $blue * (1 - 0.12 * $fadeValue) + 0.06 * $fadeValue;

        $luma = $this->luminance($red, $green, $blue);
        $saturationFactor = max(0, 1 + $parameters->saturation() / 1000);
        $red = $this->mix($luma, $red, $saturationFactor);
        $green = $this->mix($luma, $green, $saturationFactor);
        $blue = $this->mix($luma, $blue, $saturationFactor);

        $clampedRed = $this->clamp01($red);
        $clampedGreen = $this->clamp01($green);
        $clampedBlue = $this->clamp01($blue);
        $chroma = max($clampedRed, $clampedGreen, $clampedBlue) - min($clampedRed, $clampedGreen, $clampedBlue);
        $vibranceFactor = max(0, 1 + ($parameters->vibrance() / 1000) * (1 - $this->clamp01($chroma)) * 0.75);
        $luma = $this->luminance($red, $green, $blue);
        $red = $this->mix($luma, $red, $vibranceFactor);
        $green = $this->mix($luma, $green, $vibranceFactor);
        $blue = $this->mix($luma, $blue, $vibranceFactor);

        $splitLuma = $this->luminance($red, $green, $blue);
        $splitShadowMask = 1 - $this->smoothstep(0.1, 0.6, $splitLuma);
        $splitHighlightMask = $this->smoothstep(0.4, 0.9, $splitLuma);
        $shadowTone = $this->toneOffset($parameters->shadowHue());
        $highlightTone = $this->toneOffset($parameters->highlightHue());
        $shadowStrength = ($parameters->shadowStrength() / 1000) * $splitShadowMask * 0.2;
        $highlightStrength = ($parameters->highlightStrength() / 1000) * $splitHighlightMask * 0.2;

        $red += $shadowTone->red * $shadowStrength + $highlightTone->red * $highlightStrength;
        $green += $shadowTone->green * $shadowStrength + $highlightTone->green * $highlightStrength;
        $blue += $shadowTone->blue * $shadowStrength + $highlightTone->blue * $highlightStrength;

        $transformedRed = $this->clamp01($red);
        $transformedGreen = $this->clamp01($green);
        $transformedBlue = $this->clamp01($blue);
        $intensityValue = $this->clamp01($parameters->intensity() / 1000);

        return new NormalizedRgb(
            $this->clamp01($this->mix($originalRed, $transformedRed, $intensityValue)),
            $this->clamp01($this->mix($originalGreen, $transformedGreen, $intensityValue)),
            $this->clamp01($this->mix($originalBlue, $transformedBlue, $intensityValue)),
        );
    }

    public function supports(LutTransformVersion $version): bool
    {
        return match ($version) {
            LutTransformVersion::V1 => true,
        };
    }

    private function smoothstep(float $edge0, float $edge1, float $value): float
    {
        $t = $this->clamp01(($value - $edge0) / ($edge1 - $edge0));

        return $t * $t * (3 - 2 * $t);
    }

    private function luminance(float $red, float $green, float $blue): float
    {
        return $this->clamp01($red) * self::LUMA_RED
            + $this->clamp01($green) * self::LUMA_GREEN
            + $this->clamp01($blue) * self::LUMA_BLUE;
    }

    private function mix(float $a, float $b, float $amount): float
    {
        return $a * (1 - $amount) + $b * $amount;
    }

    private function hueToRgb(int $hueTenths): NormalizedRgb
    {
        $hue = fmod(fmod($hueTenths / 10, 360) + 360, 360) / 60;
        $chroma = 1.0;
        $x = $chroma * (1 - abs(fmod($hue, 2) - 1));

        if ($hue < 1) {
            return new NormalizedRgb($chroma, $x, 0);
        }

        if ($hue < 2) {
            return new NormalizedRgb($x, $chroma, 0);
        }

        if ($hue < 3) {
            return new NormalizedRgb(0, $chroma, $x);
        }

        if ($hue < 4) {
            return new NormalizedRgb(0, $x, $chroma);
        }

        if ($hue < 5) {
            return new NormalizedRgb($x, 0, $chroma);
        }

        return new NormalizedRgb($chroma, 0, $x);
    }

    private function toneOffset(int $hueTenths): RgbVector
    {
        $tone = $this->hueToRgb($hueTenths);
        $toneLuma = $tone->red * self::LUMA_RED + $tone->green * self::LUMA_GREEN + $tone->blue * self::LUMA_BLUE;

        return new RgbVector(
            $tone->red - $toneLuma,
            $tone->green - $toneLuma,
            $tone->blue - $toneLuma,
        );
    }

    private function clamp01(float $value): float
    {
        if (! is_finite($value)) {
            return 0.0;
        }

        return min(1.0, max(0.0, $value));
    }
}
