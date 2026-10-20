import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
    server: {
        host: 'localhost',
        port: 5173,
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'Modules/Builder/resources/js'),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) return
                    // Isolate only heavy leaf libraries (no cycle back into React/app).
                    // React + the rest stay on Vite's default chunking to preserve
                    // module init order.
                    if (id.includes('firebase')) return 'vendor-firebase'
                    if (id.includes('@react-google-maps')) return 'vendor-maps'
                    if (id.includes('swiper')) return 'vendor-swiper'
                    if (id.includes('react-phone-input-2')) return 'vendor-phone'
                    // Match exact package roots so react-hot-toast / react-i18next
                    // etc. are NOT swept in (that mismatch broke init order before).
                    if (/node_modules\/(react|react-dom|scheduler|@inertiajs)\//.test(id)) return 'vendor-react'
                },
            },
        },
    },
})
