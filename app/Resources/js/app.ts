import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import Layout from './Layouts/Layout.vue'
import '../css/app.css'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue', {
      eager: true,
    })
    const page = pages[`./Pages/${name}.vue`]

    if (!page) {
      throw new Error(`Wisp page not found: ${name}`)
    }

    // Persistent default layout: every Wisp page is wrapped in Layout
    // unless it opts out with `defineOptions({ layout: null })` or sets
    // its own layout. (=== undefined so an explicit null is respected.)
    const mod = page as unknown as { default: { layout?: unknown } }
    if (mod.default.layout === undefined) {
      mod.default.layout = Layout
    }

    return page
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
