<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

defineOptions({ layout: null })

const props = defineProps<{
  phpVersion: string
  branch: string
  routes: number
  canScaffold: boolean
}>()

const query = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

const examples = [
  '/create about',
  '/create service users',
  '/create repository users',
  '/create model user',
  '/remove about',
]

type Action = 'create' | 'remove' | 'docs'
type ScaffoldType =
  | 'page' | 'controller' | 'model' | 'service' | 'repository'
  | 'resource' | 'middleware' | 'provider' | 'observer' | 'dto' | 'vo'

const TYPES: ScaffoldType[] = [
  'page', 'controller', 'model', 'service', 'repository',
  'resource', 'middleware', 'provider', 'observer', 'dto', 'vo',
]

function pascalCase(s: string): string {
  const parts = s.split(/[^A-Za-z0-9]+/).filter(Boolean)
  return parts.map(p => p[0].toUpperCase() + p.slice(1).toLowerCase()).join('')
}

const parsed = computed<{ action: Action; type: ScaffoldType; name: string; url: string; docs: string }>(() => {
  const raw = query.value.trim()
  if (!raw) return { action: 'create', type: 'page', name: '', url: '', docs: '' }

  const tokens = raw.split(/\s+/).filter(Boolean)
  let action: Action = 'create'
  let i = 0

  const head = (tokens[i] ?? '').toLowerCase().replace(/^\//, '')
  if (head === 'create') { action = 'create'; i++ }
  else if (head === 'remove' || head === 'delete') { action = 'remove'; i++ }
  else if (head === 'docs') { action = 'docs'; i++ }

  // /docs <keywords> — no type/name parsing; rest is the search string.
  if (action === 'docs') {
    const docs = tokens.slice(i).join(' ').trim()
    return { action: 'docs', type: 'page', name: '', url: '', docs }
  }

  let type: ScaffoldType = 'page'
  const maybeType = (tokens[i] ?? '').toLowerCase()
  if ((TYPES as string[]).includes(maybeType)) { type = maybeType as ScaffoldType; i++ }

  const rawName = tokens.slice(i).join(' ').trim()
  const name = pascalCase(rawName)
  const url = type === 'page' && name ? '/' + name.toLowerCase() : ''

  return { action, type, name, url, docs: '' }
})

const valid = computed(() => {
  if (parsed.value.action === 'docs') return true
  return /^[A-Z][A-Za-z0-9]*$/.test(parsed.value.name)
})

const docsPreviewUrl = computed(() => {
  const q = parsed.value.docs.trim()
  return '/docs/' + (q ? '?q=' + encodeURIComponent(q) : '')
})

/* ─── Slash command suggestions ────────────────────────────────────────── */

interface Suggestion { command: string; hint: string }

const COMMANDS: Suggestion[] = [
  { command: '/create',            hint: 'Scaffold a new page (default)' },
  { command: '/create page',       hint: 'Controller + Vue page + route' },
  { command: '/create resource',   hint: 'Full CRUD: model + repository + service + page' },
  { command: '/create controller', hint: 'app/Controllers/<Name>Controller.php' },
  { command: '/create model',      hint: 'app/Models/<Name>.php' },
  { command: '/create service',    hint: 'app/Services/<Name>Service.php' },
  { command: '/create repository', hint: 'app/Repositories/<Name>Repository.php' },
  { command: '/create middleware', hint: 'app/Middlewares/<Name>.php' },
  { command: '/create provider',   hint: 'app/Providers/<Name>Provider.php' },
  { command: '/create observer',   hint: 'app/Observers/<Name>Observer.php' },
  { command: '/create dto',        hint: 'app/Dtos/<Name>Dto.php (readonly)' },
  { command: '/create vo',         hint: 'app/ValueObjects/<Name>.php (readonly)' },
  { command: '/remove',            hint: 'Tear down a scaffolded artifact' },
  { command: '/remove page',       hint: 'Remove controller + Vue + route' },
  { command: '/remove resource',   hint: 'Tear down the full CRUD bundle' },
  { command: '/remove controller', hint: 'Remove the controller file' },
  { command: '/remove model',      hint: 'Remove the model file' },
  { command: '/remove service',    hint: 'Remove the service file' },
  { command: '/remove repository', hint: 'Remove the repository file' },
  { command: '/remove middleware', hint: 'Remove the middleware file' },
  { command: '/remove provider',   hint: 'Remove the provider file' },
  { command: '/remove observer',   hint: 'Remove the observer file' },
  { command: '/remove dto',        hint: 'Remove the DTO file' },
  { command: '/remove vo',         hint: 'Remove the value object file' },
  { command: '/docs',              hint: 'Open the docs' },
  { command: '/docs routing',      hint: 'Search docs for routing' },
  { command: '/docs wisp',         hint: 'Search docs for wisp' },
  { command: '/docs scaffolder',   hint: 'Search docs for scaffolder' },
]

const activeSuggestion = ref(0)
const inputFocused = ref(false)

const suggestions = computed<Suggestion[]>(() => {
  const raw = query.value.trimStart()
  const lower = raw.toLowerCase()

  // Empty input + focused: show the top-level commands as a menu.
  if (raw === '' && inputFocused.value) {
    return COMMANDS.filter(c => /^\/(create|remove|docs)$/.test(c.command)).slice(0, 8)
  }

  // Typed past the command into a name → get out of the way.
  const passedCommand = COMMANDS.some(c => lower.startsWith(c.command + ' ') && lower.length > c.command.length + 1)
  if (passedCommand) return []

  // Prefix-match suggestions when the user has started typing a command.
  if (raw.startsWith('/')) {
    return COMMANDS
      .filter(c => c.command.startsWith(lower) || lower.startsWith(c.command + ' '))
      .slice(0, 8)
  }

  return []
})

const showSuggestions = computed(() => suggestions.value.length > 0)

function pickSuggestion(s: Suggestion): void {
  query.value = s.command + ' '
  activeSuggestion.value = 0
}

function onInputKeydown(e: KeyboardEvent): void {
  if (!showSuggestions.value) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeSuggestion.value = (activeSuggestion.value + 1) % suggestions.value.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeSuggestion.value = (activeSuggestion.value - 1 + suggestions.value.length) % suggestions.value.length
  } else if (e.key === 'Tab' || (e.key === 'Enter' && suggestions.value[activeSuggestion.value])) {
    // Tab always picks. Enter picks only when the active suggestion is a
    // strict completion of what was typed — otherwise let the form submit.
    const s = suggestions.value[activeSuggestion.value]
    const lower = query.value.toLowerCase().trim()
    if (e.key === 'Tab' || s.command !== lower) {
      e.preventDefault()
      pickSuggestion(s)
    }
  }
}

watch(suggestions, () => { activeSuggestion.value = 0 })

function onInputBlur(): void {
  // Defer so a click on a suggestion still registers before the dropdown
  // hides (mousedown.prevent on the suggestion keeps focus, but other
  // dismissal paths — clicking outside — should close the menu).
  setTimeout(() => { inputFocused.value = false }, 120)
}

const plan = computed<{ label: string; path: string }[] | null>(() => {
  const { type, name, url } = parsed.value
  if (!valid.value) return null
  switch (type) {
    case 'page':
      return [
        { label: 'Controller', path: `app/Controllers/${name}Controller.php` },
        { label: 'Page',       path: `app/Resources/js/Pages/${name}.vue` },
        { label: 'Route',      path: `GET ${url}` },
      ]
    case 'controller':
      return [{ label: 'Controller', path: `app/Controllers/${name}Controller.php` }]
    case 'model':
      return [{ label: 'Model', path: `app/Models/${name}.php` }]
    case 'service':
      return [{ label: 'Service', path: `app/Services/${name}Service.php` }]
    case 'repository':
      return [{ label: 'Repository', path: `app/Repositories/${name}Repository.php` }]
    case 'resource':
      return [
        { label: 'Model',      path: `app/Models/${name}.php` },
        { label: 'Repository', path: `app/Repositories/${name}Repository.php` },
        { label: 'Service',    path: `app/Services/${name}Service.php` },
        { label: 'Controller', path: `app/Controllers/${name}Controller.php` },
        { label: 'Page',       path: `app/Resources/js/Pages/${name}.vue` },
        { label: 'Route',      path: `GET ${url || '/' + name.toLowerCase()}` },
      ]
    case 'middleware':
      return [{ label: 'Middleware', path: `app/Middlewares/${name}.php` }]
    case 'provider':
      return [{ label: 'Provider', path: `app/Providers/${name}Provider.php` }]
    case 'observer':
      return [{ label: 'Observer', path: `app/Observers/${name}Observer.php` }]
    case 'dto':
      return [{ label: 'DTO', path: `app/Dtos/${name}Dto.php` }]
    case 'vo':
      return [{ label: 'Value Object', path: `app/ValueObjects/${name}.php` }]
  }
})

const stubs = computed(() => {
  const { type, url, name } = parsed.value
  if (!valid.value || type !== 'page') return null

  const controllerPath = `app/Controllers/${name}Controller.php`
  const vuePath = `app/Resources/js/Pages/${name}.vue`
  const routeLine = `$route->get('${url}', ${name}Controller::class);`

  const controllerStub = `<?php

declare(strict_types=1);

namespace App\\Controllers;

use Silver\\Core\\Controller;
use Silver\\Engine\\Ghost\\WispResponse;

final class ${name}Controller extends Controller
{
    public function __invoke(): WispResponse
    {
        return wisp('${name}', [
            'message' => 'Scaffolded by SilverEngine.',
        ]);
    }
}
`

  const closeScript = '<' + '/script>'
  const vueStub = `<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: null })

defineProps<{
  message: string
}>()
${closeScript}

<template>
  <div class="min-h-screen flex flex-col bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 transition-colors">
    <header class="px-8 py-5 flex items-center justify-between text-sm">
      <Link href="/" class="flex items-center gap-2 font-medium tracking-tight hover:opacity-80 transition-opacity">
        <span class="inline-block size-2 bg-zinc-900 dark:bg-zinc-100"></span>
        SilverEngine
      </Link>
      <nav class="flex items-center gap-6 text-zinc-500 dark:text-zinc-400">
        <Link href="/" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Home</Link>
        <a href="/docs/" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Docs</a>
      </nav>
    </header>

    <main class="flex-1 flex flex-col items-center px-6 py-16">
      <div class="w-full max-w-3xl">
        <p class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">${url}</p>
        <h1 class="mt-3 text-5xl font-medium tracking-tight">${name}</h1>
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
`

  return [
    { kind: 'file' as const, path: controllerPath, label: 'Controller', body: controllerStub },
    { kind: 'file' as const, path: vuePath, label: 'Page', body: vueStub },
    { kind: 'route' as const, path: `GET ${url}`, label: 'Route', body: routeLine },
  ]
})

/* ─── Theme: light | dark | auto ───────────────────────────────────────── */

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
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(STORAGE_KEY, next)
  }
  applyTheme(next)
}

function onSystemChange(): void {
  if (theme.value === 'auto') applyTheme('auto')
}

/* ─── Snippets modal ───────────────────────────────────────────────────── */

const showSnippets = ref(false)
const copied = ref<string | null>(null)

function closeSnippets(): void {
  showSnippets.value = false
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Escape' && showSnippets.value) closeSnippets()
}

watch(showSnippets, (open) => {
  if (typeof document === 'undefined') return
  document.body.style.overflow = open ? 'hidden' : ''
})

onMounted(() => {
  theme.value = readStoredTheme()
  if (typeof window !== 'undefined') {
    mql = window.matchMedia('(prefers-color-scheme: dark)')
    mql.addEventListener('change', onSystemChange)
  }
  applyTheme(theme.value)
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', onKeydown)
  }
  mql?.removeEventListener('change', onSystemChange)
})

function pick(example: string): void {
  query.value = example
}

async function copy(key: string, text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    setTimeout(() => {
      if (copied.value === key) copied.value = null
    }, 1500)
  } catch {
    // clipboard unavailable — silently ignore
  }
}

function copyAll(): void {
  if (!stubs.value) return
  const text = stubs.value
    .map(s => `// ${s.path}\n${s.body}`)
    .join('\n')
  copy('all', text)
}

async function submit(): Promise<void> {
  if (!valid.value || busy.value) return

  // /docs is a pure navigation — no server roundtrip, no canScaffold gate.
  if (parsed.value.action === 'docs') {
    const q = parsed.value.docs.trim()
    window.location.assign('/docs/' + (q ? '?q=' + encodeURIComponent(q) : ''))
    return
  }

  if (!props.canScaffold) return
  error.value = null
  busy.value = true

  const body = new FormData()
  body.append('action', parsed.value.action)
  body.append('type', parsed.value.type)
  body.append('name', parsed.value.name)

  try {
    const res = await fetch('/__silver/scaffold', {
      method: 'POST',
      headers: { 'Accept': 'application/json' },
      body,
    })

    if (!res.ok && res.status >= 400) {
      const data = await res.json().catch(() => ({ error: `HTTP ${res.status}` }))
      error.value = data.error ?? `HTTP ${res.status}`
      busy.value = false
      return
    }

    // Full page nav so Vite re-globs Pages/ and picks up the change.
    const dest = parsed.value.action === 'create' && parsed.value.type === 'page'
      ? parsed.value.url
      : '/'
    window.location.assign(dest)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Network error.'
    busy.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 transition-colors">
    <header class="relative z-10 px-8 py-5 flex items-center justify-between text-sm">
      <div class="flex items-center gap-2 font-medium tracking-tight">
        <span class="inline-block size-2 bg-zinc-900 dark:bg-zinc-100"></span>
        SilverEngine
      </div>
      <div class="flex items-center gap-6">
        <nav class="flex items-center gap-6 text-zinc-500 dark:text-zinc-400">
          <a href="/docs/" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Docs</a>
          <a href="https://github.com/SilverEngine/Framework" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">GitHub</a>
          <a href="/debug" class="cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Debug</a>
        </nav>

        <!-- Theme switcher -->
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
            <!-- Sun -->
            <svg v-if="opt === 'light'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <circle cx="12" cy="12" r="4" />
              <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <!-- Auto (half-moon split) -->
            <svg v-else-if="opt === 'auto'" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 3v18" />
              <path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" />
            </svg>
            <!-- Moon -->
            <svg v-else class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <main class="flex-1 flex flex-col items-center justify-center px-6 -mt-12">
      <div class="flex items-center gap-3 mb-10">
        <span class="inline-block size-3 bg-zinc-900 dark:bg-zinc-100"></span>
        <h1 class="text-4xl font-medium tracking-tight">SilverEngine</h1>
      </div>

      <form @submit.prevent="submit" class="w-full max-w-xl">
        <div class="relative">
        <div
          class="flex items-center gap-3 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-5 py-3.5 shadow-sm hover:shadow-md focus-within:shadow-md focus-within:border-zinc-300 dark:focus-within:border-zinc-700 transition-shadow"
        >
          <svg class="size-4 text-zinc-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
          </svg>
          <input
            v-model="query"
            @keydown="onInputKeydown"
            @focus="inputFocused = true"
            @blur="onInputBlur"
            type="text"
            placeholder="Type / for commands · /create about · /docs wisp"
            autofocus
            spellcheck="false"
            autocomplete="off"
            class="flex-1 bg-transparent outline-none text-[15px] text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500"
          />
          <button
            type="submit"
            :disabled="!valid || busy || (parsed.action !== 'docs' && !canScaffold)"
            class="text-xs font-medium px-3 py-1.5 rounded-full cursor-pointer disabled:bg-zinc-200 disabled:text-zinc-400 dark:disabled:bg-zinc-800 dark:disabled:text-zinc-600 disabled:cursor-not-allowed transition-colors"
            :class="parsed.action === 'remove'
              ? 'bg-red-600 text-white dark:bg-red-500 dark:text-white'
              : 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900'"
          >
            {{ busy
              ? (parsed.action === 'remove' ? 'Removing…' : 'Creating…')
              : (parsed.action === 'remove' ? 'Remove'
                : parsed.action === 'docs'  ? 'Search'
                : 'Build') }}
          </button>
        </div>

        <div
          v-if="showSuggestions"
          class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-20 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden"
        >
          <ul>
            <li v-for="(s, i) in suggestions" :key="s.command">
              <button
                type="button"
                @mousedown.prevent="pickSuggestion(s)"
                @mouseenter="activeSuggestion = i"
                class="w-full flex items-center gap-3 px-4 py-2 text-left cursor-pointer transition-colors"
                :class="i === activeSuggestion
                  ? 'bg-zinc-100 dark:bg-zinc-800'
                  : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/60'"
              >
                <code class="text-[13px] font-mono text-zinc-900 dark:text-zinc-100 shrink-0">{{ s.command }}</code>
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">{{ s.hint }}</span>
              </button>
            </li>
          </ul>
          <div class="px-4 py-1.5 border-t border-zinc-100 dark:border-zinc-800 text-[10px] text-zinc-400 dark:text-zinc-500">
            <kbd class="font-mono">↑↓</kbd> navigate · <kbd class="font-mono">Tab</kbd> complete · <kbd class="font-mono">Esc</kbd> dismiss
          </div>
        </div>
        </div>

        <div
          v-if="query && valid && plan && parsed.action === 'remove'"
          class="mt-4 rounded-2xl border border-red-200 dark:border-red-900/50 bg-red-50/60 dark:bg-red-950/20 overflow-hidden"
        >
          <div class="px-4 py-3 border-b border-red-200 dark:border-red-900/50">
            <span class="text-[11px] uppercase tracking-widest text-red-600 dark:text-red-400">Will delete · {{ parsed.type }}</span>
          </div>
          <ul class="divide-y divide-red-200 dark:divide-red-900/50">
            <li v-for="p in plan" :key="p.path" class="flex items-center gap-3 px-4 py-2.5">
              <span class="text-red-400 dark:text-red-500 text-xs w-20 shrink-0">{{ p.label }}</span>
              <code class="flex-1 text-[12px] text-red-700 dark:text-red-300 truncate">{{ p.path }}</code>
            </li>
          </ul>
        </div>

        <div
          v-if="query && valid && plan && parsed.action === 'create' && parsed.type !== 'page'"
          class="mt-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 overflow-hidden"
        >
          <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-[11px] uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Will create · {{ parsed.type }}</span>
          </div>
          <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
            <li v-for="p in plan" :key="p.path" class="flex items-center gap-3 px-4 py-2.5">
              <span class="text-zinc-400 dark:text-zinc-500 text-xs w-20 shrink-0">{{ p.label }}</span>
              <code class="flex-1 text-[12px] text-zinc-700 dark:text-zinc-300 truncate">{{ p.path }}</code>
            </li>
          </ul>
        </div>

        <div
          v-if="query && valid && stubs && parsed.action === 'create' && parsed.type === 'page'"
          class="mt-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 overflow-hidden"
        >
          <div class="flex items-center justify-between px-4 py-2.5 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-[11px] uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Preview</span>
            <button
              type="button"
              @click="showSnippets = true"
              class="text-[11px] text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 cursor-pointer transition-colors"
            >
              Show snippets →
            </button>
          </div>

          <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
            <li
              v-for="s in stubs"
              :key="s.path"
              class="flex items-center gap-3 px-4 py-2.5"
            >
              <span class="text-zinc-400 dark:text-zinc-500 text-xs w-12 shrink-0">{{ s.label }}</span>
              <code class="flex-1 text-[12px] text-zinc-700 dark:text-zinc-300 truncate">{{ s.path }}</code>
              <button
                type="button"
                @click="copy(s.path, s.body)"
                :title="`Copy ${s.label.toLowerCase()} contents`"
                class="text-[11px] text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 cursor-pointer transition-colors shrink-0"
              >
                {{ copied === s.path ? 'Copied' : 'Copy' }}
              </button>
            </li>
          </ul>

          <div class="flex items-center justify-between px-4 py-2.5 border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <span class="text-[11px] text-zinc-400 dark:text-zinc-500">
              Hit
              <kbd class="px-1 py-0.5 rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-mono">Build</kbd>
              to create — or copy and do it yourself.
            </span>
            <button
              type="button"
              @click="copyAll"
              class="text-[11px] font-medium text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-zinc-100 cursor-pointer transition-colors"
            >
              {{ copied === 'all' ? 'Copied all' : 'Copy all' }}
            </button>
          </div>
        </div>

        <div v-if="query && !valid && parsed.action !== 'docs'" class="mt-3 text-xs text-amber-600 dark:text-amber-400 px-5">
          Need a name. Try <code>/create about</code> or <code>/create service users</code>.
        </div>

        <div v-if="query && valid && parsed.action === 'docs'" class="mt-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 p-4">
          <span class="text-[11px] uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Will open</span>
          <code class="block mt-1.5 text-[12px] text-zinc-700 dark:text-zinc-300">{{ docsPreviewUrl }}</code>
        </div>

        <div v-if="!canScaffold" class="mt-3 text-xs text-zinc-500 dark:text-zinc-400 px-5">
          Scaffolding is disabled. Set <code>APP_ENV=local</code> and <code>APP_DEBUG=true</code> to enable.
        </div>

        <div v-if="error" class="mt-3 text-xs text-red-600 dark:text-red-400 px-5">{{ error }}</div>
      </form>

      <div class="mt-8 flex flex-wrap items-center justify-center gap-2 max-w-xl">
        <button
          v-for="ex in examples"
          :key="ex"
          type="button"
          @click="pick(ex)"
          class="text-xs px-3 py-1.5 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 cursor-pointer hover:border-zinc-300 dark:hover:border-zinc-700 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors"
        >
          {{ ex }}
        </button>
      </div>

      <p class="mt-12 max-w-md text-center text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
        Type a route. We scaffold a controller, a Vue page, and wire the route —
        then drop you on it. No CLI, no setup.
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

    <!-- Snippets modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        leave-active-class="transition duration-100 ease-in"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showSnippets && stubs"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/50 backdrop-blur-sm"
          @click.self="closeSnippets"
          role="dialog"
          aria-modal="true"
          aria-label="Generated snippets"
        >
          <div
            class="w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-2xl overflow-hidden"
          >
            <div class="flex items-center justify-between px-5 py-3 border-b border-zinc-200 dark:border-zinc-800">
              <div>
                <h2 class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Generated snippets</h2>
                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                  Copy these into your project, or hit Build to apply them.
                </p>
              </div>
              <button
                type="button"
                @click="closeSnippets"
                aria-label="Close"
                class="size-7 grid place-items-center rounded-full text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800 cursor-pointer transition-colors"
              >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M18 6 6 18M6 6l12 12" />
                </svg>
              </button>
            </div>

            <div class="overflow-y-auto themed-scroll divide-y divide-zinc-100 dark:divide-zinc-800">
              <div v-for="s in stubs" :key="`snip-${s.path}`" class="p-5">
                <div class="flex items-center justify-between mb-2">
                  <code class="text-[11px] text-zinc-600 dark:text-zinc-300">{{ s.path }}</code>
                  <button
                    type="button"
                    @click="copy(`snip-${s.path}`, s.body)"
                    class="text-[11px] text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 cursor-pointer transition-colors"
                  >
                    {{ copied === `snip-${s.path}` ? 'Copied' : 'Copy' }}
                  </button>
                </div>
                <pre class="text-[12px] leading-relaxed text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-md px-3 py-2 overflow-x-auto"><code>{{ s.body }}</code></pre>
              </div>
            </div>

            <div class="flex items-center justify-between px-5 py-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-950/40">
              <span class="text-[11px] text-zinc-400 dark:text-zinc-500">Esc to close</span>
              <button
                type="button"
                @click="copyAll"
                class="text-xs font-medium px-3 py-1.5 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 cursor-pointer transition-colors"
              >
                {{ copied === 'all' ? 'Copied all' : 'Copy all' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style>
/* Themed scrollbar for the snippets modal. Firefox + Chromium. */
.themed-scroll {
  scrollbar-width: thin;
  scrollbar-color: rgb(212 212 216) transparent;
}
.themed-scroll::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.themed-scroll::-webkit-scrollbar-track {
  background: transparent;
}
.themed-scroll::-webkit-scrollbar-thumb {
  background: rgb(212 212 216);
  border-radius: 4px;
}
.themed-scroll::-webkit-scrollbar-thumb:hover {
  background: rgb(161 161 170);
}
:where(.dark) .themed-scroll {
  scrollbar-color: rgb(63 63 70) transparent;
}
:where(.dark) .themed-scroll::-webkit-scrollbar-thumb {
  background: rgb(63 63 70);
}
:where(.dark) .themed-scroll::-webkit-scrollbar-thumb:hover {
  background: rgb(82 82 91);
}
</style>
