<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: null })

defineProps<{
  message: string
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

const pillars = [
  {
    title: 'Zero ceremony',
    body: 'Composer is only the autoloader. No framework code from Packagist — everything lives in this repo.',
  },
  {
    title: 'Server-driven Vue',
    body: 'Wisp + Inertia. No client router, no separate API layer. Controllers return pages, not JSON.',
  },
  {
    title: 'PHP 8.4, no apologies',
    body: 'Strict types, readonly, enums, match. Final classes by default. Modern PHP, end to end.',
  },
]
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
          <Link href="/" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Home</Link>
          <a href="https://silverengine.net/docs" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Docs</a>
          <a href="https://github.com/SilverEngine/Framework" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">GitHub</a>
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

    <main class="flex-1 flex flex-col items-center px-6 py-16">
      <div class="w-full max-w-3xl">
        <p class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">/about</p>
        <h1 class="mt-3 text-5xl font-medium tracking-tight">A small framework, on purpose.</h1>
        <p class="mt-6 text-lg leading-relaxed text-zinc-600 dark:text-zinc-400 max-w-2xl">
          {{ message }} SilverEngine is a PHP 8.4 DMVC framework built on a single rule —
          no dependency you wouldn't write yourself.
        </p>

        <div class="mt-14 grid gap-4 sm:grid-cols-3">
          <div
            v-for="p in pillars"
            :key="p.title"
            class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 p-5 hover:border-zinc-300 dark:hover:border-zinc-700 transition-colors"
          >
            <div class="flex items-center gap-2 mb-2">
              <span class="inline-block size-1.5 bg-zinc-900 dark:bg-zinc-100"></span>
              <h2 class="text-sm font-medium tracking-tight">{{ p.title }}</h2>
            </div>
            <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">{{ p.body }}</p>
          </div>
        </div>

        <div class="mt-14 flex flex-wrap items-center gap-3">
          <Link
            href="/"
            class="text-xs font-medium px-4 py-2 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 hover:opacity-90 transition-opacity"
          >
            ← Back to scaffolder
          </Link>
          <a
            href="https://github.com/SilverEngine/Framework"
            class="text-xs font-medium px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 hover:border-zinc-300 dark:hover:border-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
          >
            Star on GitHub
          </a>
        </div>
      </div>
    </main>

    <footer class="px-8 py-5 flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-500 border-t border-zinc-100 dark:border-zinc-900">
      <span>&copy; {{ new Date().getFullYear() }} SilverEngine · MIT</span>
      <span>Built with Wisp.</span>
    </footer>
  </div>
</template>
