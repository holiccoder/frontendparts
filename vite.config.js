import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            // ziggy-js is a Composer package; the tsconfig paths mapping
            // covers types only, so Vite needs the alias for real imports
            // (the SSR bundle builds route() from the shared ziggy prop).
            'ziggy-js': fileURLToPath(new URL('./vendor/tightenco/ziggy', import.meta.url)),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
});
