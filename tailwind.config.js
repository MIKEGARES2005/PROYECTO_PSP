import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            transitionDuration: {
                75: '60ms',
                100: '80ms',
                150: '120ms',
                200: '150ms',
                300: '190ms',
                500: '240ms',
                700: '300ms',
                750: '300ms',
                1000: '350ms',
            },
            transitionDelay: {
                300: '60ms',
                400: '80ms',
            },
        },
    },

    plugins: [forms],
};
