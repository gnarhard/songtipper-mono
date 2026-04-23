<?php

declare(strict_types=1);

test('approved filled-container color pairs meet wcag aa contrast', function () {
    $path = base_path('../_shared/design/theme_tokens.json');
    if (! file_exists($path)) {
        $this->markTestSkipped('theme_tokens.json not available outside monorepo');
    }

    $tokens = json_decode(
        file_get_contents($path),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $pairs = $tokens['filledForegroundPairs'];

    foreach ($pairs as $platform => $modes) {
        foreach ($modes as $mode => $modePairs) {
            $palette = $tokens[$platform][$mode];

            foreach ($modePairs as $backgroundToken => $foregroundToken) {
                $background = $palette[$backgroundToken];
                $foreground = $palette[$foregroundToken];
                $ratio = contrast_ratio($background, $foreground);

                expect($ratio)->toBeGreaterThanOrEqual(
                    4.5,
                    "{$platform}.{$mode}.{$backgroundToken}/{$foregroundToken} contrast {$ratio}",
                );
            }
        }
    }
});

function contrast_ratio(string $background, string $foreground): float
{
    $backgroundLuminance = relative_luminance($background);
    $foregroundLuminance = relative_luminance($foreground);
    $lighter = max($backgroundLuminance, $foregroundLuminance);
    $darker = min($backgroundLuminance, $foregroundLuminance);

    return ($lighter + 0.05) / ($darker + 0.05);
}

function relative_luminance(string $hex): float
{
    [$red, $green, $blue] = hex_to_rgb($hex);

    return 0.2126 * linear_channel($red)
        + 0.7152 * linear_channel($green)
        + 0.0722 * linear_channel($blue);
}

function linear_channel(int $value): float
{
    $normalized = $value / 255;

    if ($normalized <= 0.03928) {
        return $normalized / 12.92;
    }

    return (($normalized + 0.055) / 1.055) ** 2.4;
}

function hex_to_rgb(string $hex): array
{
    $normalized = ltrim($hex, '#');

    return [
        hexdec(substr($normalized, 0, 2)),
        hexdec(substr($normalized, 2, 2)),
        hexdec(substr($normalized, 4, 2)),
    ];
}
