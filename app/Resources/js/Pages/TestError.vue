<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: null })

defineProps<{
  message: string
}>()
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 transition-colors">
    <header class="px-8 py-5 flex items-center justify-between text-sm">
      <Link href="/" class="flex items-center gap-2 font-medium tracking-tight hover:opacity-80 transition-opacity">
        <span class="inline-block size-2 bg-zinc-900 dark:bg-zinc-100"></span>
        SilverEngine
      </Link>
      <nav class="flex items-center gap-6 text-zinc-500 dark:text-zinc-400">
        <Link href="/" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Home</Link>
        <a href="https://silverengine.net/docs" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Docs</a>
      </nav>
    </header>

    <main class="flex-1 flex flex-col items-center px-6 py-16">
      <div class="w-full max-w-3xl">
        <p class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">/testerror</p>
        <h1 class="mt-3 text-5xl font-medium tracking-tight">TestError</h1>
        <p class="mt-6 text-lg leading-relaxed text-zinc-600 dark:text-zinc-400 max-w-2xl">
          {{ message }}
        </p>

        <div class="mt-14">
          <Link
            href="/"
            class="text-xs font-medium px-4 py-2 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 hover:opacity-90 transition-opacity"
          >
            ← Back home
          </Link>
        </div>
      </div>
    </main>

    <footer class="px-8 py-5 flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-500 border-t border-zinc-100 dark:border-zinc-900">
      <span>&copy; {{ new Date().getFullYear() }} SilverEngine · MIT</span>
      <span>Built with Wisp.</span>
    </footer>
  </div>
</template>
