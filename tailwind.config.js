import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#F0F6EC',
                    100: '#DBEBD0',
                    200: '#BAD8A6',
                    300: '#93C077',
                    400: '#6BA34C',
                    500: '#4C8431',
                    600: '#3A6A26',
                    700: '#2E5622',
                    800: '#26451D',
                    900: '#1C3416',
                },
                sand: {
                    50: '#FBFAF7',
                    100: '#F4F2EB',
                    200: '#E7E4DA',
                    300: '#D5D1C4',
                    400: '#A9A597',
                    500: '#7C7869',
                    600: '#5A5750',
                    700: '#403E38',
                    800: '#2A2925',
                    900: '#1A1917',
                },
            },
        },
    },

    plugins: [forms],
};
