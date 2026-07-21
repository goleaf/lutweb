import type { CanonicalLutParameters } from '@/types/lut-wizard';

export interface Rgb {
    red: number;
    green: number;
    blue: number;
}

const LUMA = Object.freeze({
    red: 0.2126,
    green: 0.7152,
    blue: 0.0722,
});

function finite(value: number): number {
    return Number.isFinite(value) ? value : 0;
}

export function clamp01(value: number): number {
    return Math.min(1, Math.max(0, finite(value)));
}

function smoothstep(edge0: number, edge1: number, value: number): number {
    const t = clamp01((value - edge0) / (edge1 - edge0));

    return t * t * (3 - 2 * t);
}

function luminance(rgb: Rgb): number {
    return (
        clamp01(rgb.red) * LUMA.red +
        clamp01(rgb.green) * LUMA.green +
        clamp01(rgb.blue) * LUMA.blue
    );
}

function mix(a: number, b: number, amount: number): number {
    return a * (1 - amount) + b * amount;
}

function hueToRgb(hueTenths: number): Rgb {
    const hue = ((((hueTenths / 10) % 360) + 360) % 360) / 60;
    const chroma = 1;
    const x = chroma * (1 - Math.abs((hue % 2) - 1));

    if (hue < 1) {
        return { red: chroma, green: x, blue: 0 };
    }

    if (hue < 2) {
        return { red: x, green: chroma, blue: 0 };
    }

    if (hue < 3) {
        return { red: 0, green: chroma, blue: x };
    }

    if (hue < 4) {
        return { red: 0, green: x, blue: chroma };
    }

    if (hue < 5) {
        return { red: x, green: 0, blue: chroma };
    }

    return { red: chroma, green: 0, blue: x };
}

function toneOffset(hueTenths: number): Rgb {
    const tone = hueToRgb(hueTenths);
    const toneLuma =
        tone.red * LUMA.red + tone.green * LUMA.green + tone.blue * LUMA.blue;

    return {
        red: tone.red - toneLuma,
        green: tone.green - toneLuma,
        blue: tone.blue - toneLuma,
    };
}

export function transformV1(
    input: Rgb,
    parameters: CanonicalLutParameters,
): Rgb {
    const original = {
        red: clamp01(input.red),
        green: clamp01(input.green),
        blue: clamp01(input.blue),
    };
    const rgb = { ...original };

    const exposureEv = parameters.exposure / 100;
    const exposureGain = 2 ** exposureEv;
    rgb.red *= exposureGain;
    rgb.green *= exposureGain;
    rgb.blue *= exposureGain;

    const temperatureValue = parameters.temperature / 1000;
    const tintValue = parameters.tint / 1000;
    const redGain = Math.max(
        0.1,
        1 + 0.18 * temperatureValue + 0.03 * tintValue,
    );
    const greenGain = Math.max(0.1, 1 - 0.1 * tintValue);
    const blueGain = Math.max(
        0.1,
        1 - 0.18 * temperatureValue + 0.03 * tintValue,
    );
    rgb.red *= redGain;
    rgb.green *= greenGain;
    rgb.blue *= blueGain;

    const contrastGain = 2 ** (parameters.contrast / 1000);
    rgb.red = (rgb.red - 0.5) * contrastGain + 0.5;
    rgb.green = (rgb.green - 0.5) * contrastGain + 0.5;
    rgb.blue = (rgb.blue - 0.5) * contrastGain + 0.5;

    let luma = luminance(rgb);
    const shadowMask = 1 - smoothstep(0.1, 0.6, luma);
    const highlightMask = smoothstep(0.4, 0.9, luma);
    const blackMask = 1 - smoothstep(0, 0.25, luma);
    const whiteMask = smoothstep(0.75, 1, luma);
    const toneOffsetValue =
        (parameters.shadows / 1000) * 0.22 * shadowMask +
        (parameters.highlights / 1000) * 0.22 * highlightMask +
        (parameters.blacks / 1000) * 0.18 * blackMask +
        (parameters.whites / 1000) * 0.18 * whiteMask;
    rgb.red += toneOffsetValue;
    rgb.green += toneOffsetValue;
    rgb.blue += toneOffsetValue;

    const fadeValue = parameters.fade / 1000;
    rgb.red = rgb.red * (1 - 0.12 * fadeValue) + 0.06 * fadeValue;
    rgb.green = rgb.green * (1 - 0.12 * fadeValue) + 0.06 * fadeValue;
    rgb.blue = rgb.blue * (1 - 0.12 * fadeValue) + 0.06 * fadeValue;

    luma = luminance(rgb);
    const saturationFactor = Math.max(0, 1 + parameters.saturation / 1000);
    rgb.red = mix(luma, rgb.red, saturationFactor);
    rgb.green = mix(luma, rgb.green, saturationFactor);
    rgb.blue = mix(luma, rgb.blue, saturationFactor);

    const clampedForChroma = {
        red: clamp01(rgb.red),
        green: clamp01(rgb.green),
        blue: clamp01(rgb.blue),
    };
    const chroma =
        Math.max(
            clampedForChroma.red,
            clampedForChroma.green,
            clampedForChroma.blue,
        ) -
        Math.min(
            clampedForChroma.red,
            clampedForChroma.green,
            clampedForChroma.blue,
        );
    const vibranceFactor = Math.max(
        0,
        1 + (parameters.vibrance / 1000) * (1 - clamp01(chroma)) * 0.75,
    );
    luma = luminance(rgb);
    rgb.red = mix(luma, rgb.red, vibranceFactor);
    rgb.green = mix(luma, rgb.green, vibranceFactor);
    rgb.blue = mix(luma, rgb.blue, vibranceFactor);

    const splitLuma = luminance(rgb);
    const splitShadowMask = 1 - smoothstep(0.1, 0.6, splitLuma);
    const splitHighlightMask = smoothstep(0.4, 0.9, splitLuma);
    const shadowTone = toneOffset(parameters.shadow_hue);
    const highlightTone = toneOffset(parameters.highlight_hue);
    const shadowStrength =
        (parameters.shadow_strength / 1000) * splitShadowMask * 0.2;
    const highlightStrength =
        (parameters.highlight_strength / 1000) * splitHighlightMask * 0.2;

    rgb.red +=
        shadowTone.red * shadowStrength + highlightTone.red * highlightStrength;
    rgb.green +=
        shadowTone.green * shadowStrength +
        highlightTone.green * highlightStrength;
    rgb.blue +=
        shadowTone.blue * shadowStrength +
        highlightTone.blue * highlightStrength;

    const transformed = {
        red: clamp01(rgb.red),
        green: clamp01(rgb.green),
        blue: clamp01(rgb.blue),
    };
    const intensityValue = clamp01(parameters.intensity / 1000);

    return {
        red: clamp01(mix(original.red, transformed.red, intensityValue)),
        green: clamp01(mix(original.green, transformed.green, intensityValue)),
        blue: clamp01(mix(original.blue, transformed.blue, intensityValue)),
    };
}

export function withoutIntensity(
    parameters: CanonicalLutParameters,
): CanonicalLutParameters {
    return {
        ...parameters,
        intensity: 1000,
    };
}
