import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import { tailwindPalettes } from './theme/generated_theme_tokens.js';

const apricot = tailwindPalettes.apricot;
const light = tailwindPalettes.light;
const dark = tailwindPalettes.dark;
const success = tailwindPalettes.success;
const danger = tailwindPalettes.danger;
const info = tailwindPalettes.info;

const buildAccentScale = (palette) => ({
    DEFAULT: palette.normal,
    50: palette.accent,
    100: palette.accent,
    200: palette.accent,
    300: palette.accent,
    500: palette.normal,
    600: palette.normal,
    700: palette.normal,
    800: palette.normal,
    900: palette.normal,
});

const accentScale = {
    DEFAULT: dark.accent,
    50: light.normal,
    100: light.accent,
    200: light.accent,
    300: light.accent,
    500: dark.accent,
    700: dark.accent,
    800: dark.separator,
    900: dark.normal,
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand: buildAccentScale(apricot),
                action: buildAccentScale(apricot),
                accent: accentScale,
                success: buildAccentScale(success),
                danger: {
                    ...buildAccentScale(danger),
                    950: danger.normal,
                },
                info: buildAccentScale(info),
                red: {
                    ...buildAccentScale(danger),
                    950: danger.normal,
                },
                canvas: {
                    light: light.normal,
                    dark: dark.normal,
                },
                surface: {
                    DEFAULT: light.normal,
                    muted: light.accent,
                    subtle: light.accent,
                    inverse: dark.accent,
                    elevated: dark.separator,
                },
                ink: {
                    DEFAULT: dark.normal,
                    muted: dark.accent,
                    subtle: dark.accent,
                    inverse: light.normal,
                    soft: light.accent,
                    border: light.accent,
                    'border-dark': dark.separator,
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Raleway', 'Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
