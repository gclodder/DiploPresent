<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  Copy,
  GraduationCap,
  MonitorUp,
  RotateCcw,
  Trash2,
} from 'lucide-vue-next'
import { useRoute, useRouter } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { api } from '../api/client'
import { normalizeJson, photoUrl } from '../domain/students'
import { useUiStore } from '../stores/ui'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const sessionId = String(route.params.sessionId)
const controllerToken = sessionStorage.getItem(`diplopresent.controller.${sessionId}`) || ''
const session = ref(null)
const students = ref([])
const busy = ref(false)
const pollTimer = ref(null)
const config = ref({ photoBaseUrl: 'storage/photos' })

const liveUrl = computed(() => {
  const resolved = router.resolve({ name: 'live', query: { session: sessionId } })
  return new URL(resolved.href, window.location.href).href
})
const fallbackPhoto = computed(() => `${import.meta.env.BASE_URL}images/no-photo.jpg`)

async function load() {
  try {
    session.value = await api.getSession(sessionId)
    const [list, loadedConfig] = await Promise.all([
      api.getList(session.value.listName),
      api.getConfig(),
    ])
    students.value = normalizeJson(list.content)
    config.value = loadedConfig
  } catch (error) {
    ui.notify(error.message)
  }
}

async function update(changes) {
  if (!controllerToken || busy.value) return
  busy.value = true
  try {
    session.value = await api.updateSession(sessionId, controllerToken, changes)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    busy.value = false
  }
}

function previous() {
  update({ index: Math.max(-1, (session.value?.index ?? -1) - 1), peek: false, testPattern: false })
}

function next() {
  update({
    index: Math.min(students.value.length - 1, (session.value?.index ?? -1) + 1),
    peek: false,
    testPattern: false,
  })
}

function jumpTo(index) {
  update({ index, peek: false, testPattern: false })
}

function toggleTestPattern() {
  update({ testPattern: !session.value?.testPattern, peek: false })
}

function newPhotoUrl(student) {
  return photoUrl(config.value.photoBaseUrl, student.studentNumber, 2)
}

function useFallback(event) {
  event.target.src = fallbackPhoto.value
}

function openLive() {
  window.open(liveUrl.value, '_blank', 'noopener')
}

async function copyLiveUrl() {
  await navigator.clipboard.writeText(liveUrl.value)
  ui.notify('Gedeelde beamer-URL gekopieerd.')
}

async function killSession() {
  if (!controllerToken || busy.value) return
  const confirmed = window.confirm(
    'Weet je zeker dat je deze live sessie wilt stoppen? Het beamerbeeld verliest dan de koppeling met dit dashboard.',
  )
  if (!confirmed) return

  busy.value = true
  try {
    await api.deleteSession(sessionId, controllerToken)
    sessionStorage.removeItem(`diplopresent.controller.${sessionId}`)
    if (sessionStorage.getItem('diplopresent.activeSession') === sessionId) {
      sessionStorage.removeItem('diplopresent.activeSession')
    }
    ui.notify('Live sessie gestopt.')
    await router.push('/presenter')
  } catch (error) {
    ui.notify(error.message)
  } finally {
    busy.value = false
  }
}

function onKeydown(event) {
  if (event.key === 'ArrowRight') next()
  if (event.key === 'ArrowLeft') previous()
  if (event.key === '0') update({ index: -1, peek: false, testPattern: false })
  if (event.key.toLowerCase() === 'v' && !session.value?.peek) update({ peek: true })
  if (event.key.toLowerCase() === 't' && !event.repeat) toggleTestPattern()
}

function onKeyup(event) {
  if (event.key.toLowerCase() === 'v' && session.value?.peek) update({ peek: false })
}

onMounted(async () => {
  if (!controllerToken) {
    ui.notify('De controllercode ontbreekt. Start de gedeelde modus opnieuw via Presenter.')
    return
  }
  await load()
  pollTimer.value = window.setInterval(async () => {
    try {
      session.value = await api.getSession(sessionId)
    } catch {
      // Een tijdelijke pollingfout hoeft de bediening niet te onderbreken.
    }
  }, 1500)
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('keyup', onKeyup)
})

onBeforeUnmount(() => {
  if (pollTimer.value) window.clearInterval(pollTimer.value)
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('keyup', onKeyup)
})
</script>

<template>
  <main class="page-shell">
    <AppHeader compact />
    <div v-if="session" class="content-width grid gap-6 lg:grid-cols-[1fr_22rem]">
      <section class="space-y-5">
        <div class="panel">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-bold uppercase tracking-wide text-navy">Presentatordashboard</p>
              <h1 class="text-2xl font-bold">{{ session.title }}</h1>
              <p class="muted">{{ session.listName }} · sessie {{ session.id }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button class="button-secondary border-navy text-navy" @click="copyLiveUrl">
                <Copy :size="18" /> Kopieer URL
              </button>
              <button class="button-primary" @click="openLive">
                <MonitorUp :size="18" /> Open beamerbeeld
              </button>
            </div>
          </div>
        </div>

        <section class="panel">
          <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 class="text-xl font-bold">Direct springen</h2>
              <p class="muted">Klik op een leerling om die direct op het beamerscherm te tonen.</p>
            </div>
            <p class="text-sm font-semibold text-slate-500">{{ students.length }} leerlingen</p>
          </div>

          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
            <button
              v-for="(student, index) in students"
              :key="student.studentNumber || `${student.fullName}-${index}`"
              type="button"
              class="group rounded-2xl border bg-white p-2 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-wait"
              :class="
                session.index === index
                  ? 'border-navy ring-4 ring-navy/20'
                  : 'border-slate-200 hover:border-navy/40'
              "
              :disabled="busy"
              @click="jumpTo(index)"
            >
              <div class="relative aspect-[4/5] overflow-hidden rounded-xl bg-slate-100">
                <img
                  :src="newPhotoUrl(student)"
                  :alt="student.fullName"
                  class="h-full w-full object-cover transition group-hover:scale-105"
                  @error="useFallback"
                />
                <span
                  v-if="student.cumLaude"
                  class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-gold px-2 py-1 text-[0.65rem] font-black uppercase tracking-wide text-ink shadow"
                >
                  <GraduationCap :size="13" />
                  Cum laude
                </span>
              </div>
              <p class="mt-2 line-clamp-2 min-h-10 text-center text-sm font-bold leading-tight text-slate-900">
                {{ student.fullName }}
              </p>
              <p
                v-if="session.index === index"
                class="mt-1 rounded-full bg-navy px-2 py-1 text-center text-xs font-bold text-white"
              >
                Actief
              </p>
            </button>
          </div>
        </section>
      </section>

      <aside class="space-y-5">
        <section class="panel">
          <p class="text-sm font-bold uppercase tracking-wide text-navy">Bediening</p>
          <button
            class="button-secondary mt-4 w-full border-navy text-navy"
            :disabled="busy"
            @click="jumpTo(-1)"
          >
            <RotateCcw :size="18" />
            Toon titelpagina
          </button>
          <button
            class="mt-3 w-full"
            :class="session.testPattern ? 'button-primary' : 'button-secondary border-navy text-navy'"
            :disabled="busy"
            @click="toggleTestPattern"
          >
            {{ session.testPattern ? 'Verberg testbeeld' : 'Toon testbeeld' }}
          </button>
          <button
            class="button-secondary mt-4 w-full border-red-300 text-red-700 hover:bg-red-100"
            :disabled="busy"
            @click="killSession"
          >
            <Trash2 :size="18" />
            Sessie stoppen
          </button>
          <p class="muted mt-2 text-sm">
            ← vorige · → volgende · 0 titelpagina · V preview · T testbeeld
          </p>
        </section>

        
      </aside>
    </div>
  </main>
</template>
