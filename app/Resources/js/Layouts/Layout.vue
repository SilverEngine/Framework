<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen flex flex-col bg-slate-950 text-slate-100">
    <header
      class="flex items-center justify-between px-6 py-4 border-b border-slate-800"
    >
      <Link
        href="/"
        class="text-sm font-semibold tracking-tight hover:text-indigo-400 transition-colors"
      >
        SilverEngine
      </Link>
      <nav class="flex items-center gap-5 text-xs uppercase tracking-widest text-slate-500">
        <Link href="/" class="hover:text-slate-200 transition-colors">Home</Link>
        <Link href="/wisp-demo" prefetch class="hover:text-slate-200 transition-colors">
          Wisp Demo
        </Link>
      </nav>
    </header>

    <main class="flex-1 flex flex-col items-center justify-center gap-6 px-6 py-16">
      <slot />
    </main>

    <footer
      class="px-6 py-4 border-t border-slate-800 text-center text-xs text-slate-600"
    >
      Powered by Wisp — server-driven Vue, baked into Ghost.
    </footer>
  </div>
</template>
