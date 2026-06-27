<script setup>
import { computed, onMounted, ref } from 'vue'
import { ExternalLink, FileJson, MonitorUp, Plus, Presentation } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { api } from '../api/client'
import { normalizeJson } from '../domain/students'
import { usePresentationStore } from '../stores/presentation'
import { useUiStore } from '../stores/ui'

const ui = useUiStore()
const router = useRouter()
const presentation = usePresentationStore()
const files = ref([])
const selectedFile = ref('')
const loading = ref(false)
const config = ref({ photoBaseUrl: 'storage/photos', defaultTitle: '' })
const fallback = `${import.meta.env.BASE_URL}images/no-photo.jpg`

const groupPhoto = computed(
  () =>
    `${import.meta.env.BASE_URL}${config.value.photoBaseUrl}/examenfoto_${presentation.department}.jpg`,
)

onMounted(async () => {
  presentation.restore()
  try {
    ;[files.value, config.value] = await Promise.all([
      api.getFiles('lists'),
      api.getConfig(),
    ])
    if (presentation.listName && files.value.some((file) => file.name === presentation.listName)) {
      selectedFile.value = presentation.listName
    }
    if (!presentation.title) {
      presentation.title = config.value.defaultTitle
    }
  } catch (error) {
    ui.notify(error.message)
  }
})

async function loadList() {
  if (!selectedFile.value) {
    presentation.students = []
    return
  }
  loading.value = true
  try {
    const list = await api.getList(selectedFile.value)
    presentation.listName = selectedFile.value
    presentation.students = normalizeJson(list.content)
    presentation.photoBaseUrl = config.value.photoBaseUrl
    presentation.persist()
    ui.notify(`${presentation.students.length} leerlingen geladen.`)
  } catch (error) {
    presentation.students = []
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

async function selectList(fileName) {
  selectedFile.value = fileName
  await loadList()
}

function createNewList() {
  router.push('/editor')
}

function persistSettings() {
  presentation.photoBaseUrl = config.value.photoBaseUrl
  presentation.persist()
}

function startPresentation() {
  if (!presentation.students.length) return
  persistSettings()
  const url = new URL(
    `${import.meta.env.BASE_URL}#/live/${encodeURIComponent(presentation.listName)}`,
    window.location.href,
  )
  window.open(url, '_blank', 'noopener')
}

async function startSharedPresentation() {
  if (!presentation.students.length) return
  persistSettings()
  loading.value = true
  try {
    const result = await api.createSession({
      listName: presentation.listName,
      title: presentation.title,
      department: presentation.department,
    })
    sessionStorage.setItem(
      `diplopresent.controller.${result.session.id}`,
      result.controllerToken,
    )
    await router.push(`/dashboard/${result.session.id}`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="page-shell">
    <AppHeader compact />
    <div class="content-width grid gap-6 lg:grid-cols-3">
      <section class="panel">
        <span class="mb-3 block text-sm font-bold uppercase tracking-wide text-navy">Stap 1</span>
        <h1 class="text-xl font-bold">Kies een lijst</h1>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
          <button
            v-for="file in files"
            :key="file.name"
            type="button"
            class="rounded-2xl border bg-white p-4 text-left text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-wait"
            :class="
              selectedFile === file.name
                ? 'border-navy ring-4 ring-navy/20'
                : 'border-slate-200 hover:border-navy/40'
            "
            :disabled="loading"
            @click="selectList(file.name)"
          >
            <div class="mb-3 flex items-start justify-between gap-3">
              <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gold/50 text-navy">
                <FileJson :size="26" />
              </span>
              <span class="rounded-full bg-navy px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">
                .json
              </span>
            </div>
            <p class="break-words font-bold leading-tight">{{ file.name }}</p>
            <p class="muted mt-2">
              {{
                Number.isFinite(file.studentCount)
                  ? `${file.studentCount} leerlingen`
                  : 'Aantal leerlingen onbekend'
              }}
            </p>
          </button>

          <button
            type="button"
            class="grid min-h-40 place-items-center rounded-2xl border-2 border-dashed border-slate-300 bg-white/70 p-4 text-center text-ink transition hover:-translate-y-0.5 hover:border-navy hover:bg-white hover:shadow-lg"
            @click="createNewList"
          >
            <span>
              <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-navy text-white">
                <Plus :size="32" />
              </span>
              <span class="block font-bold">Nieuwe lijst maken</span>
              <span class="mt-1 block text-sm text-slate-500">Ga naar Editor</span>
            </span>
          </button>
        </div>
        <p class="muted mt-3">
          {{ loading ? 'Laden…' : `${presentation.students.length} leerlingen geladen` }}
        </p>
      </section>

      <section class="panel" :class="{ 'pointer-events-none opacity-50': !presentation.students.length }">
        <span class="mb-3 block text-sm font-bold uppercase tracking-wide text-navy">Stap 2</span>
        <h2 class="text-xl font-bold">Titelpagina</h2>
        <label class="mt-4 block">
          <span class="mb-1 block font-medium">Titel</span>
          <input v-model.trim="presentation.title" class="field" @change="persistSettings" />
        </label>
        <fieldset class="mt-4">
          <legend class="mb-2 font-medium">Afdeling</legend>
          <div class="grid grid-cols-2 gap-2">
            <label
              v-for="department in ['havo', 'vwo']"
              :key="department"
              class="cursor-pointer rounded-lg border p-2 text-center font-bold uppercase"
              :class="
                presentation.department === department
                  ? 'border-gold-dark bg-gold'
                  : 'border-slate-300'
              "
            >
              <input
                v-model="presentation.department"
                class="sr-only"
                type="radio"
                :value="department"
                @change="persistSettings"
              />
              {{ department }}
            </label>
          </div>
        </fieldset>
        <img
          :src="groupPhoto"
          alt="Voorbeeld groepsfoto"
          class="mt-4 aspect-video w-full rounded-xl object-cover"
          @error="($event.target.src = fallback)"
        />
      </section>

      <section class="panel flex flex-col" :class="{ 'pointer-events-none opacity-50': !presentation.students.length }">
        <span class="mb-3 block text-sm font-bold uppercase tracking-wide text-navy">Stap 3</span>
        <Presentation :size="52" class="text-navy" />
        <h2 class="mt-4 text-xl font-bold">Start presentatie</h2>
        <p class="mt-2 text-slate-600">
          Kies lokaal voor één laptop, of gedeeld voor een apart dashboard en beamerbeeld.
        </p>
        <div class="mt-5 grid gap-3">
          <button class="button-primary" :disabled="!presentation.students.length" @click="startPresentation">
            Eén laptop <ExternalLink :size="18" />
          </button>
          <button
            class="button-secondary border-navy text-navy hover:bg-navy hover:text-white"
            :disabled="!presentation.students.length"
            @click="startSharedPresentation"
          >
            Dashboard + gedeelde URL <MonitorUp :size="18" />
          </button>
        </div>
      </section>
    </div>
  </main>
</template>
