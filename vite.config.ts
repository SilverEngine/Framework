import { defineConfig, type Plugin, type ViteDevServer } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { writeFileSync, rmSync, mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

const BUILD_DIR = resolve('public/build')
const HOT_FILE = resolve(BUILD_DIR, 'hot')

/**
 * Writes public/build/hot with the dev-server origin while `vite` runs, so the
 * PHP-side {{ vite() }} helper knows to emit HMR tags. Removed on shutdown.
 */
function hotFile(): Plugin {
  const clean = () => {
    try {
      rmSync(HOT_FILE)
    } catch {
      /* already gone */
    }
  }

  return {
    name: 'wisp-hot-file',
    configureServer(server: ViteDevServer) {
      server.httpServer?.once('listening', () => {
        const address = server.httpServer?.address()
        const port =
          address && typeof address === 'object' ? address.port : 5173
        const protocol = server.config.server.https ? 'https' : 'http'
        mkdirSync(BUILD_DIR, { recursive: true })
        writeFileSync(HOT_FILE, `${protocol}://localhost:${port}`)
      })

      const exit = () => {
        clean()
        process.exit()
      }
      process.once('SIGINT', exit)
      process.once('SIGTERM', exit)
      process.once('exit', clean)
    },
  }
}

export default defineConfig({
  plugins: [vue(), tailwindcss(), hotFile()],
  resolve: {
    alias: { '@': resolve('App/Resources/js') },
  },
  // Static assets are served by PHP from public/assets — Vite's publicDir
  // would otherwise recursively copy public/ into public/build.
  publicDir: false,
  server: {
    origin: 'http://localhost:5173',
  },
  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
    rollupOptions: {
      input: 'App/Resources/js/app.ts',
    },
  },
})
