import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/charts.js', 'resources/js/lesson-editor.js', 'resources/js/push.js', 'resources/js/pwa-install.js', 'resources/js/install-app.js', 'resources/js/solid-geometry.js', 'resources/js/plane-geometry.js', 'resources/js/question-geometry.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
