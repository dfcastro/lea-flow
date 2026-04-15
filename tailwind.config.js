import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Livewire/**/*.php',
        './app/Models/**/*.php', 
        './resources/views/welcome.blade.php',// Importante para as cores que vêm do Model
    ],

    theme: {
        extend: {
            // As suas novas cores institucionais do Lacerda e Associados
            colors: {
                lacerda: {
                    dark: '#1a2d2f',
                    gold: '#b99b5e',
                    teal: '#007a7f',
                }
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // Adicionando a animação que usamos nas telas do L&A Flow
            animation: {
                fadeIn: 'fadeIn 0.5s ease-out forwards',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
        },
    },

    plugins: [forms],
};