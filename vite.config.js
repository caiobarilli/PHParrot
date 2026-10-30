import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const portaNoHost = Number(process.env.VITE_HOST_PORT ?? 5174);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: true,
        port: 5173,
        strictPort: true,
        origin: `http://localhost:${portaNoHost}`,
        hmr: {
            host: 'localhost',
            clientPort: portaNoHost,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
