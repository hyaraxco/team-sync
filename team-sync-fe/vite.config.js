import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig(({ mode }) => ({
    plugins: [
        vue(),
        vueJsx(),
        ...(mode === 'development' ? [vueDevTools()] : []),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        },
    },
    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', 'vue-router', 'pinia'],
                    'vendor-charts': ['apexcharts', 'vue3-apexcharts'],
                    'vendor-pdf': ['jspdf', 'jspdf-autotable'],
                    'vendor-utils': ['luxon', 'lodash-es', 'sortablejs'],
                    'vendor-icons': ['lucide-vue-next'],
                },
            },
        },
    },
}))
