<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: null })

defineProps<{
  phpVersion: string
  branch: string
  routes: number
  canScaffold: boolean
}>()

type ThemeChoice = 'light' | 'dark' | 'auto'
const STORAGE_KEY = 'silverengine-theme'
const theme = ref<ThemeChoice>('auto')
let mql: MediaQueryList | null = null

function readStoredTheme(): ThemeChoice {
  if (typeof localStorage === 'undefined') return 'auto'
  const v = localStorage.getItem(STORAGE_KEY)
  return v === 'light' || v === 'dark' || v === 'auto' ? v : 'auto'
}

function applyTheme(choice: ThemeChoice): void {
  if (typeof document === 'undefined') return
  const dark = choice === 'dark' || (choice === 'auto' && mql?.matches === true)
  document.documentElement.classList.toggle('dark', dark)
}

function setTheme(next: ThemeChoice): void {
  theme.value = next
  if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY, next)
  applyTheme(next)
}

function onSystemChange(): void {
  if (theme.value === 'auto') applyTheme('auto')
}

onMounted(() => {
  theme.value = readStoredTheme()
  if (typeof window !== 'undefined') {
    mql = window.matchMedia('(prefers-color-scheme: dark)')
    mql.addEventListener('change', onSystemChange)
  }
  applyTheme(theme.value)
})

onUnmounted(() => {
  mql?.removeEventListener('change', onSystemChange)
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 transition-colors">
    <header class="relative z-10 px-8 py-5 flex items-center justify-between text-sm">
      <Link href="/" class="flex items-center gap-2 font-medium tracking-tight hover:opacity-80 transition-opacity">
        <span class="inline-block size-2 bg-zinc-900 dark:bg-zinc-100"></span>
        SilverEngine
      </Link>
      <div class="flex items-center gap-6">
        <nav class="flex items-center gap-6 text-zinc-500 dark:text-zinc-400">
          <a href="/docs/" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Docs</a>
          <a href="https://github.com/SilverEngine/Framework" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">GitHub</a>
          <a href="/debug" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Debug</a>
        </nav>

        <div
          role="group"
          aria-label="Color theme"
          class="flex items-center gap-0.5 rounded-full border border-zinc-200 dark:border-zinc-800 p-0.5"
        >
          <button
            v-for="opt in (['light', 'auto', 'dark'] as const)"
            :key="opt"
            type="button"
            :aria-pressed="theme === opt"
            :title="`${opt[0].toUpperCase()}${opt.slice(1)} theme`"
            @click="setTheme(opt)"
            class="size-7 rounded-full grid place-items-center cursor-pointer transition-colors text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100"
            :class="theme === opt ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : ''"
          >
            <svg v-if="opt === 'light'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="4" />
              <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <svg v-else-if="opt === 'auto'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 3v18" />
              <path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" />
            </svg>
            <svg v-else class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <main class="flex-1 flex flex-col items-center justify-center px-6 -mt-12">
      <div class="flex items-center gap-3 mb-8">
        <span class="inline-block size-3 bg-zinc-900 dark:bg-zinc-100"></span>
        <h1 class="text-5xl font-medium tracking-tight">SilverEngine</h1>
      </div>

      <p class="max-w-xl text-center text-base text-zinc-500 dark:text-zinc-400 leading-relaxed">
        A small PHP 8.5 framework, on purpose. Server-driven Vue via Wisp,
        no Packagist deps, no client router.
      </p>

      <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
        <a
          v-if="canScaffold"
          href="/new"
          class="text-xs font-medium px-4 py-2 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 hover:opacity-90 transition-opacity"
        >
          Open scaffolder →
        </a>
        <a
          href="/docs/"
          class="text-xs font-medium px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 hover:border-zinc-300 dark:hover:border-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
        >
          Documentation
        </a>
        <a
          href="https://github.com/SilverEngine/Framework"
          class="text-xs font-medium px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 hover:border-zinc-300 dark:hover:border-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
        >
          GitHub
        </a>
      </div>

      <p v-if="!canScaffold" class="mt-8 text-xs text-zinc-400 dark:text-zinc-500">
        Scaffolder available in <code>APP_ENV=local</code> + <code>APP_DEBUG=true</code>.
      </p>
    </main>

    <footer class="px-8 py-5 flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-500 tabular-nums border-t border-zinc-100 dark:border-zinc-900">
      <span>&copy; {{ new Date().getFullYear() }} SilverEngine · MIT</span>
      <span class="flex items-center gap-5">
        <span>{{ branch }}</span>
        <span>{{ routes }} routes</span>
        <span>php {{ phpVersion }}</span>
      </span>
    </footer>
  </div>
</template>
