import '../css/app.css'
import '@/i18n'

import axios from 'axios'
import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createRoot } from 'react-dom/client'

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
window.axios = axios

createInertiaApp({
    resolve: (name) => {
        const pageName = name.replace(/^Builder::/, '')
        return resolvePageComponent(
            `../../Modules/Builder/resources/js/Pages/${pageName}.jsx`,
            import.meta.glob('../../Modules/Builder/resources/js/Pages/**/*.jsx'),
        )
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />)
    },
})
