import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Colours are driven by CSS custom properties holding raw RGB channels
 * (e.g. `--c-brand-600: 79 70 229`) so that every Tailwind utility keeps
 * working with opacity modifiers (`bg-brand-600/10`) *and* flips
 * automatically in dark mode.
 */
const c = (name) => `rgb(var(${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                /* ---- Structural surfaces ---- */
                canvas: c('--c-canvas'),
                surface: {
                    DEFAULT: c('--c-surface'),
                    sunk: c('--c-surface-sunk'),
                    raised: c('--c-surface-raised'),
                },
                /* alias kept so pre-existing `bg-paper-sunk` markup still resolves */
                paper: {
                    DEFAULT: c('--c-canvas'),
                    sunk: c('--c-surface-sunk'),
                },

                /* ---- Hairlines ---- */
                line: {
                    DEFAULT: c('--c-border'),
                    strong: c('--c-border-strong'),
                },

                /* ---- Text ---- */
                ink: {
                    DEFAULT: c('--c-ink'),
                    soft: c('--c-muted'),
                    faint: c('--c-faint'),
                },
                muted: c('--c-muted'),
                faint: c('--c-faint'),

                /* ---- Brand: a single calm indigo, used sparingly ---- */
                brand: {
                    50: c('--c-brand-50'),
                    100: c('--c-brand-100'),
                    200: c('--c-brand-200'),
                    300: c('--c-brand-300'),
                    400: c('--c-brand-400'),
                    500: c('--c-brand-500'),
                    600: c('--c-brand-600'),
                    700: c('--c-brand-700'),
                    DEFAULT: c('--c-brand-600'),
                },
                accent: c('--c-brand-600'),

                /* ---- Semantic status ---- */
                success: { DEFAULT: c('--c-success'), soft: c('--c-success-soft') },
                warning: { DEFAULT: c('--c-warning'), soft: c('--c-warning-soft') },
                danger: { DEFAULT: c('--c-danger'), soft: c('--c-danger-soft') },
                info: { DEFAULT: c('--c-info'), soft: c('--c-info-soft') },
                ok: c('--c-success'),
            },

            borderRadius: {
                sm: '0.25rem',
                DEFAULT: '0.375rem',
                md: '0.5rem',
                lg: '0.625rem',
                xl: '0.75rem',
                '2xl': '1rem',
            },

            /* Deliberately shallow — depth is communicated with hairlines, not shadow. */
            boxShadow: {
                xs: '0 1px 2px 0 rgb(16 24 40 / 0.04)',
                sm: '0 1px 3px 0 rgb(16 24 40 / 0.06), 0 1px 2px -1px rgb(16 24 40 / 0.04)',
                pop: '0 8px 24px -6px rgb(16 24 40 / 0.12), 0 2px 6px -2px rgb(16 24 40 / 0.06)',
                none: 'none',
            },

            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem' }],
            },

            spacing: {
                sidebar: '16rem',
                topbar: '3.75rem',
            },

            maxWidth: {
                content: '90rem',
            },

            keyframes: {
                'fade-in': {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
                'slide-up': {
                    from: { opacity: '0', transform: 'translateY(4px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-in': 'fade-in .15s ease-out',
                'slide-up': 'slide-up .15s ease-out',
            },
        },
    },

    plugins: [forms],
};
