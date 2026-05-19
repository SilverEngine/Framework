<script setup lang="ts">
import { Link, Deferred } from '@inertiajs/vue3'

defineProps<{
  message: string
  appName: string
  serverTime: string
  stats?: { routes: number }
}>()
</script>

<template>
  <div
    class="min-h-screen flex flex-col items-center justify-center gap-6 bg-slate-950 text-slate-100"
  >
    <p class="text-xs uppercase tracking-widest text-slate-500">{{ appName }}</p>
    <h1 class="text-4xl font-bold tracking-tight">{{ message }}</h1>
    <p class="text-slate-400">Server time: {{ serverTime }}</p>

    <Deferred data="stats">
      <template #fallback>
        <p class="text-slate-600 text-sm">Loading stats…</p>
      </template>
      <p class="text-indigo-400 text-sm">
        Registered routes: {{ stats?.routes }}
      </p>
    </Deferred>

    <Link
      href="/wisp-demo"
      prefetch
      class="px-5 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 transition-colors"
    >
      Reload via Wisp (prefetched)
    </Link>
  </div>
</template>
