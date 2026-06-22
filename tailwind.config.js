import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
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
            colors: {
                gray: {
                    150: '#eceef1',
                    250: '#dadde2',
                    405: '#9ca3af',
                    550: '#6b7280',
                    605: '#424c5a',
                    650: '#424a57',
                    750: '#2b3544',
                    850: '#18202c',
                },
                violet: {
                    650: '#7431e3',
                },
                indigo: {
                    650: '#493fe0',
                    750: '#3d34b7',
                },
                teal: {
                    650: '#0e857b',
                },
            },
        },
    },

    plugins: [forms],
};
