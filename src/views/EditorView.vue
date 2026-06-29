<script setup>
import { computed, onMounted, ref } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import {
  ArrowDownAZ,
  Download,
  FileJson,
  FileSpreadsheet,
  GraduationCap,
  GripVertical,
  Plus,
  Search,
  Shuffle,
  Trash2,
  UploadCloud,
} from 'lucide-vue-next'
import AppHeader from '../components/AppHeader.vue'
import PhotoPair from '../components/PhotoPair.vue'
import { api } from '../api/client'
import { normalizeJson, parseCsv, renumber, safeListName, selectStudents } from '../domain/students'
import { useUiStore } from '../stores/ui'

const ui = useUiStore()
const csvFiles = ref([])
const jsonFiles = ref([])
const editorMode = ref('csv')
const selectedFile = ref('')
const selectedJsonFile = ref('')
const sourceStudents = ref([])
const listType = ref('mentor')
const selectedChoices = ref([])
const listStudents = ref([])
const search = ref('')
const loading = ref(false)
const saving = ref(false)
const uploadInput = ref(null)
const config = ref({ photoBaseUrl: 'storage/photos' })

const mentors = computed(() =>
  [...new Set(sourceStudents.value.map((student) => student.mentor).filter(Boolean))].sort((a, b) =>
    a.localeCompare(b, 'nl'),
  ),
)
const groups = computed(() =>
  [...new Set(sourceStudents.value.map((student) => student.group).filter(Boolean))].sort((a, b) =>
    a.localeCompare(b, 'nl', { numeric: true }),
  ),
)
const availableChoices = computed(() => (listType.value === 'mentor' ? mentors.value : groups.value))
const visibleStudents = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase('nl')
  if (!needle) return listStudents.value
  return listStudents.value.filter((student) =>
    [student.fullName, student.lastName, student.studentNumber].some((value) =>
      value.toLocaleLowerCase('nl').includes(needle),
    ),
  )
})
const proposedName = computed(() =>
  editorMode.value === 'json' && selectedJsonFile.value
    ? selectedJsonFile.value
    : safeListName(selectedChoices.value),
)
const listTypeLabel = computed(() => (listType.value === 'mentor' ? 'mentor' : 'stamgroep'))
const canSave = computed(() => listStudents.value.length > 0 && proposedName.value)
const hasFirstNames = computed(() => listStudents.value.some((student) => student.firstName))

function displayFileName(fileName) {
  return fileName.replace(/\.[^.]+$/u, '')
}

onMounted(async () => {
  await Promise.all([loadFiles(), loadConfig()])
})

async function loadConfig() {
  try {
    config.value = await api.getConfig()
  } catch (error) {
    ui.notify(error.message)
  }
}

async function loadFiles() {
  try {
    ;[csvFiles.value, jsonFiles.value] = await Promise.all([
      api.getFiles('imports'),
      api.getFiles('lists'),
    ])
  } catch (error) {
    ui.notify(error.message)
  }
}

function resetSelection() {
  selectedChoices.value = []
  listStudents.value = []
  search.value = ''
}

function resetEditor() {
  selectedFile.value = ''
  selectedJsonFile.value = ''
  sourceStudents.value = []
  resetSelection()
}

function switchMode(mode) {
  editorMode.value = mode
  resetEditor()
}

async function loadSelected() {
  if (!selectedFile.value) {
    sourceStudents.value = []
    resetSelection()
    return
  }
  loading.value = true
  try {
    const imported = await api.getImport(selectedFile.value)
    sourceStudents.value = parseCsv(imported.content)
    resetSelection()
    ui.notify(`${sourceStudents.value.length} leerlingen geladen. Kies nu mentor(en) of een stamgroep.`)
  } catch (error) {
    sourceStudents.value = []
    resetSelection()
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

async function selectFile(fileName) {
  editorMode.value = 'csv'
  selectedJsonFile.value = ''
  selectedFile.value = fileName
  await loadSelected()
}

async function selectJsonFile(fileName) {
  editorMode.value = 'json'
  selectedFile.value = ''
  sourceStudents.value = []
  selectedJsonFile.value = fileName
  loading.value = true
  try {
    const list = await api.getList(fileName)
    listStudents.value = normalizeJson(list.content)
    listType.value = list.content.listType || 'mentor'
    selectedChoices.value = Array.isArray(list.content.selection) && list.content.selection.length
      ? list.content.selection
      : [fileName.replace(/\.json$/i, '')]
    search.value = ''
    ui.notify(`${listStudents.value.length} leerlingen geladen uit ${fileName}.`)
  } catch (error) {
    resetSelection()
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

async function uploadFile(event) {
  const file = event.target.files?.[0]
  if (!file) return
  loading.value = true
  try {
    const result = await api.uploadImport(file)
    await loadFiles()
    editorMode.value = 'csv'
    selectedJsonFile.value = ''
    selectedFile.value = result.name
    await loadSelected()
    ui.notify(`${result.name} geüpload; een bestaand bestand is indien nodig vervangen.`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    event.target.value = ''
    loading.value = false
  }
}

async function deleteCsv(fileName) {
  if (!window.confirm(`${fileName} verwijderen?`)) return
  loading.value = true
  try {
    await api.deleteImport(fileName)
    if (selectedFile.value === fileName) resetEditor()
    await loadFiles()
    ui.notify(`${fileName} verwijderd.`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

async function deleteJson(fileName) {
  if (!window.confirm(`${fileName} verwijderen?`)) return
  loading.value = true
  try {
    await api.deleteList(fileName)
    if (selectedJsonFile.value === fileName) resetEditor()
    await loadFiles()
    ui.notify(`${fileName} verwijderd.`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

function chooseGroup(value) {
  if (listType.value === 'mentor') {
    selectedChoices.value = selectedChoices.value.includes(value)
      ? selectedChoices.value.filter((choice) => choice !== value)
      : [...selectedChoices.value, value]
  } else {
    selectedChoices.value = [value]
  }
  const field = listType.value === 'mentor' ? 'mentor' : 'group'
  listStudents.value = selectStudents(sourceStudents.value, field, selectedChoices.value)
  search.value = ''
}

function shuffle() {
  for (let index = listStudents.value.length - 1; index > 0; index -= 1) {
    const random = Math.floor(Math.random() * (index + 1))
    ;[listStudents.value[index], listStudents.value[random]] = [
      listStudents.value[random],
      listStudents.value[index],
    ]
  }
  renumber(listStudents.value)
}

function sortByLastName() {
  listStudents.value.sort((a, b) => a.lastName.localeCompare(b.lastName, 'nl'))
  renumber(listStudents.value)
}

function removeStudent(student) {
  listStudents.value = listStudents.value.filter((value) => value !== student)
  renumber(listStudents.value)
}

function toggleCumLaude(student) {
  student.cumLaude = !student.cumLaude
}

async function saveList() {
  if (!canSave.value) return
  saving.value = true
  try {
    await api.saveList(proposedName.value, listStudents.value, {
      listType: listType.value,
      selection: selectedChoices.value,
    })
    await loadFiles()
    if (editorMode.value === 'json') selectedJsonFile.value = proposedName.value
    ui.notify(`${proposedName.value} opgeslagen in de getoonde volgorde.`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    saving.value = false
  }
}

function downloadList() {
  const blob = new Blob(
    [
      JSON.stringify(
        {
          version: 1,
          listType: listType.value,
          selection: selectedChoices.value,
          students: listStudents.value,
        },
        null,
        2,
      ),
    ],
    { type: 'application/json' },
  )
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = proposedName.value
  link.click()
  URL.revokeObjectURL(link.href)
}
</script>

<template>
  <main class="page-shell">
    <AppHeader compact />
    <div class="content-width space-y-6">
      <section class="panel">
        <div class="mb-5">
          <h1 class="text-lg font-bold">1. Kies je bron</h1>
          <p class="muted mt-1">
            Maak een nieuwe lijst vanuit een CSV, of open een bestaande JSON-presentatielijst om de volgorde/cum laude te bewerken.
          </p>
          <div class="mt-4 grid max-w-xl grid-cols-2 gap-2">
            <button
              class="rounded-lg border p-3 text-center font-semibold"
              :class="editorMode === 'csv' ? 'border-gold-dark bg-gold text-ink' : 'border-slate-300'"
              type="button"
              @click="switchMode('csv')"
            >
              Nieuwe lijst uit CSV
            </button>
            <button
              class="rounded-lg border p-3 text-center font-semibold"
              :class="editorMode === 'json' ? 'border-gold-dark bg-gold text-ink' : 'border-slate-300'"
              type="button"
              @click="switchMode('json')"
            >
              Bestaande JSON bewerken
            </button>
          </div>
        </div>

        <input
          ref="uploadInput"
          class="hidden"
          type="file"
          accept=".csv"
          @change="uploadFile"
        />

        <div v-if="editorMode === 'csv'" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          <article
            v-for="file in csvFiles"
            :key="file.name"
            class="rounded-2xl border bg-white p-4 text-left text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-wait"
            :class="
              selectedFile === file.name
                ? 'border-navy ring-4 ring-navy/20'
                : 'border-slate-200 hover:border-navy/40'
            "
            @click="selectFile(file.name)"
          >
            <div class="mb-4 flex items-start justify-between gap-3">
              <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/50 text-navy">
                <FileSpreadsheet :size="28" />
              </span>
              <span class="flex items-center gap-1">
                <span class="rounded-full bg-navy px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">
                  .csv
                </span>
                <button
                  class="rounded-full p-1.5 text-red-700 hover:bg-red-100"
                  type="button"
                  :disabled="loading"
                  :aria-label="`${file.name} verwijderen`"
                  @click.stop="deleteCsv(file.name)"
                >
                  <Trash2 :size="16" />
                </button>
              </span>
            </div>
            <p class="break-words font-bold leading-tight">{{ displayFileName(file.name) }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <p
                v-if="selectedFile === file.name"
                class="inline-flex rounded-full bg-navy px-3 py-1 text-xs font-bold text-white"
              >
                Geselecteerd
              </p>
            </div>
          </article>

          <button
            type="button"
            class="grid min-h-40 place-items-center rounded-2xl border-2 border-dashed border-slate-300 bg-white/70 p-4 text-center text-ink transition hover:-translate-y-0.5 hover:border-navy hover:bg-white hover:shadow-lg disabled:cursor-wait"
            :disabled="loading"
            @click="uploadInput?.click()"
          >
            <span>
              <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-navy text-white">
                <Plus :size="32" />
              </span>
              <span class="block font-bold">Nieuwe CSV uploaden</span>
              <span class="mt-1 block text-sm text-slate-500">of bestaande vervangen</span>
            </span>
          </button>
        </div>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          <article
            v-for="file in jsonFiles"
            :key="file.name"
            class="rounded-2xl border bg-white p-4 text-left text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
            :class="
              selectedJsonFile === file.name
                ? 'border-navy ring-4 ring-navy/20'
                : 'border-slate-200 hover:border-navy/40'
            "
            @click="selectJsonFile(file.name)"
          >
            <div class="mb-3 flex items-start justify-between gap-3">
              <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/50 text-navy">
                <FileJson :size="28" />
              </span>
              <span class="flex items-center gap-1">
                <span class="rounded-full bg-navy px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">
                  .json
                </span>
                <button
                  class="rounded-full p-1.5 text-red-700 hover:bg-red-100"
                  type="button"
                  :disabled="loading"
                  :aria-label="`${file.name} verwijderen`"
                  @click.stop="deleteJson(file.name)"
                >
                  <Trash2 :size="16" />
                </button>
              </span>
            </div>
            <p class="break-words font-bold leading-tight">{{ displayFileName(file.name) }}</p>
            <p class="muted mt-2">
              {{
                Number.isFinite(file.studentCount)
                  ? `${file.studentCount} leerlingen`
                  : 'Aantal leerlingen onbekend'
              }}
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <p
                v-if="selectedJsonFile === file.name"
                class="inline-flex rounded-full bg-navy px-3 py-1 text-xs font-bold text-white"
              >
                Geselecteerd
              </p>
            </div>
          </article>

          <button
            type="button"
            class="grid min-h-40 place-items-center rounded-2xl border-2 border-dashed border-slate-300 bg-white/70 p-4 text-center text-ink transition hover:-translate-y-0.5 hover:border-navy hover:bg-white hover:shadow-lg"
            @click="switchMode('csv')"
          >
            <span>
              <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-navy text-white">
                <Plus :size="32" />
              </span>
              <span class="block font-bold">Nieuwe lijst maken</span>
              <span class="mt-1 block text-sm text-slate-500">Start vanuit CSV</span>
            </span>
          </button>
        </div>
      </section>

      <section v-if="sourceStudents.length" class="panel">
        <h2 class="text-lg font-bold">2. Kies het soort lijst</h2>
        <div class="mt-3 grid max-w-lg grid-cols-2 gap-2">
          <label
            v-for="option in [
              { value: 'mentor', label: 'Mentor(en) combineren' },
              { value: 'group', label: 'Per stamgroep' },
            ]"
            :key="option.value"
            class="cursor-pointer rounded-lg border p-3 text-center font-semibold"
            :class="listType === option.value ? 'border-gold-dark bg-gold' : 'border-slate-300'"
          >
            <input
              v-model="listType"
              class="sr-only"
              type="radio"
              :value="option.value"
              @change="resetSelection"
            />
            {{ option.label }}
          </label>
        </div>
        <h3 class="mt-5 font-bold">
          Kies {{ listType === 'mentor' ? 'één of meer mentoren' : 'één stamgroep' }}
        </h3>
        <p class="muted mt-1">
          {{
            listType === 'mentor'
              ? 'Alle leerlingen van de gekozen mentoren worden samengevoegd in één lijst.'
              : 'Alle leerlingen uit de gekozen stamgroep komen in één lijst.'
          }}
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
          <button
            v-for="choice in availableChoices"
            :key="choice"
            class="rounded-full border px-4 py-2 text-sm font-semibold transition"
            :class="
              selectedChoices.includes(choice)
                ? 'border-gold-dark bg-gold text-ink'
                : 'border-slate-300 hover:border-gold'
            "
            @click="chooseGroup(choice)"
          >
            {{ choice }}
          </button>
        </div>
        <p v-if="listType === 'group' && !groups.length" class="mt-4 text-amber-700">
          Dit importbestand bevat geen kolom <code>Stamgroep</code>. Kies een CSV-jaarbestand waarin die kolom aanwezig is.
        </p>
      </section>

      <section v-if="selectedChoices.length" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold">
              3. Bepaal de volgorde voor {{ selectedChoices.join(' + ') }}
            </h2>
            <p class="text-sm text-white/70">
              {{ listStudents.length }} leerlingen · wordt opgeslagen als {{ proposedName }}
            </p>
          </div>
          <label class="relative min-w-64 flex-1 lg:max-w-sm">
            <Search class="absolute left-3 top-2.5 text-slate-500" :size="20" />
            <input v-model="search" class="field pl-10" placeholder="Zoek binnen deze lijst…" />
          </label>
        </div>

        <div class="flex flex-wrap gap-3">
          <button class="button-secondary" @click="sortByLastName">
            <ArrowDownAZ :size="18" /> Sorteer op achternaam
          </button>
          <button class="button-secondary" @click="shuffle"><Shuffle :size="18" /> Schudden</button>
          <button class="button-secondary" @click="downloadList">
            <Download :size="18" /> Download
          </button>
          <button class="button-primary" :disabled="saving || !canSave" @click="saveList">
            <UploadCloud :size="18" /> {{ saving ? 'Opslaan…' : `${proposedName} opslaan` }}
          </button>
        </div>

        <p v-if="search" class="text-sm text-gold">
          Wis de zoekopdracht om de volgorde met drag-and-drop te wijzigen.
        </p>
        <div class="overflow-x-auto rounded-xl bg-white text-ink shadow-xl">
          <table class="w-full min-w-[850px] border-collapse">
            <thead class="bg-slate-100 text-left text-sm uppercase tracking-wide text-slate-600">
              <tr>
                <th class="p-3">Volgorde</th>
                <th class="p-3">Foto’s</th>
                <th class="p-3">Leerlingnummer</th>
                <th class="p-3">Naam</th>
                <th v-if="hasFirstNames" class="p-3">Achternaam</th>
                <th class="p-3">Cum laude</th>
                <th class="p-3"><span class="sr-only">Acties</span></th>
              </tr>
            </thead>
            <VueDraggable
              v-if="!search"
              v-model="listStudents"
              tag="tbody"
              handle=".drag-handle"
              @end="renumber(listStudents)"
            >
              <tr
                v-for="student in listStudents"
                :key="student.studentNumber"
                class="border-t border-slate-200 hover:bg-gold/10"
              >
                <td class="p-3">
                  <span class="drag-handle inline-flex cursor-grab items-center gap-1 font-semibold">
                    <GripVertical :size="18" class="text-slate-400" /> {{ student.position }}
                  </span>
                </td>
                <td class="p-3">
                  <PhotoPair :student-number="student.studentNumber" :photo-base-url="config.photoBaseUrl" />
                </td>
                <td class="p-3">{{ student.studentNumber }}</td>
                <td class="p-3 font-medium">{{ student.firstName || student.fullName }}</td>
                <td v-if="hasFirstNames" class="p-3">{{ student.lastName }}</td>
                <td class="p-3">
                  <button
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                    :class="
                      student.cumLaude
                        ? 'border-gold-dark bg-gold text-ink'
                        : 'border-slate-300 text-slate-500 hover:border-gold'
                    "
                    :aria-pressed="student.cumLaude"
                    @click="toggleCumLaude(student)"
                  >
                    <GraduationCap :size="18" />
                    {{ student.cumLaude ? 'Ja' : 'Nee' }}
                  </button>
                </td>
                <td class="p-3">
                  <button
                    class="rounded-lg p-2 text-red-700 hover:bg-red-100"
                    aria-label="Leerling verwijderen"
                    @click="removeStudent(student)"
                  >
                    <Trash2 :size="18" />
                  </button>
                </td>
              </tr>
            </VueDraggable>
            <tbody v-else>
              <tr
                v-for="student in visibleStudents"
                :key="student.studentNumber"
                class="border-t border-slate-200"
              >
                <td class="p-3">{{ student.position }}</td>
                <td class="p-3">
                  <PhotoPair :student-number="student.studentNumber" :photo-base-url="config.photoBaseUrl" />
                </td>
                <td class="p-3">{{ student.studentNumber }}</td>
                <td class="p-3 font-medium">{{ student.firstName || student.fullName }}</td>
                <td v-if="hasFirstNames" class="p-3">{{ student.lastName }}</td>
                <td class="p-3">
                  <button
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                    :class="
                      student.cumLaude
                        ? 'border-gold-dark bg-gold text-ink'
                        : 'border-slate-300 text-slate-500 hover:border-gold'
                    "
                    :aria-pressed="student.cumLaude"
                    @click="toggleCumLaude(student)"
                  >
                    <GraduationCap :size="18" />
                    {{ student.cumLaude ? 'Ja' : 'Nee' }}
                  </button>
                </td>
                <td class="p-3">
                  <button
                    class="rounded-lg p-2 text-red-700 hover:bg-red-100"
                    aria-label="Leerling verwijderen"
                    @click="removeStudent(student)"
                  >
                    <Trash2 :size="18" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</template>
