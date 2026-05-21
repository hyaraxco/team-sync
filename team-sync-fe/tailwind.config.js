/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './index.html',
        './src/**/*.{vue,js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
                // Semantic tokens — reference CSS custom properties for dark mode support
                'brand-dark': 'var(--color-brand-dark)',
                'brand-light': 'var(--color-brand-light)',
                'brand-border': 'var(--color-brand-border)',
                'brand-primary': 'var(--color-brand-primary)',
                // Surface tokens — bg-white, bg-gray-50, bg-gray-100 etc. via CSS vars
                'surface': 'var(--color-surface)',
                'surface-raised': 'var(--color-surface-raised)',
                'surface-overlay': 'var(--color-surface-overlay)',
                'surface-muted': 'var(--color-surface-muted)',
                'surface-subtle': 'var(--color-surface-subtle)',
                // Border tokens
                'border-default': 'var(--color-border-default)',
                'border-muted': 'var(--color-border-muted)',
                // Text tokens
                'text-primary': 'var(--color-text-primary)',
                'text-secondary': 'var(--color-text-secondary)',
                'text-muted': 'var(--color-text-muted)',
                'text-disabled': 'var(--color-text-disabled)',
                success: {
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                },
                warning: {
                    50: '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                },
            },
            borderRadius: {
                'card': '20px',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(-10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                fadeIn: 'fadeIn 0.3s ease-out',
            },
        },
    },
    plugins: [],
}
