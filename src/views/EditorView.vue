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
  Pencil,
  Plus,
  Search,
  Sparkles,
  Shuffle,
  Trash2,
  UploadCloud,
  X,
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
const mergeSelectedJsonFiles = ref([])
const mergeStudents = ref([])
const newlyAddedStudentKeys = ref(new Set())
const addStudentModalOpen = ref(false)
const newStudent = ref({
  studentNumber: '',
  fullName: '',
  firstName: '',
  lastName: '',
  mentor: '',
  group: '',
  cumLaude: false,
})
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
const mergeProposedName = computed(() =>
  safeListName(mergeSelectedJsonFiles.value.map((fileName) => displayFileName(fileName))),
)
const listTypeLabel = computed(() => (listType.value === 'mentor' ? 'mentor' : 'stamgroep'))
const canSave = computed(() => listStudents.value.length > 0 && proposedName.value)
const canSaveMerged = computed(() => mergeSelectedJsonFiles.value.length > 1 && mergeStudents.value.length > 0)
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
  newlyAddedStudentKeys.value = new Set()
  search.value = ''
}

function resetEditor() {
  selectedFile.value = ''
  selectedJsonFile.value = ''
  mergeSelectedJsonFiles.value = []
  mergeStudents.value = []
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
    newlyAddedStudentKeys.value = new Set()
    ui.notify(`${listStudents.value.length} leerlingen geladen uit ${fileName}.`)
  } catch (error) {
    resetSelection()
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

async function rebuildMergePreview() {
  mergeStudents.value = []
  if (!mergeSelectedJsonFiles.value.length) return
  loading.value = true
  try {
    const merged = []
    for (const fileName of mergeSelectedJsonFiles.value) {
      const list = await api.getList(fileName)
      merged.push(...normalizeJson(list.content))
    }
    mergeStudents.value = renumber(merged)
  } catch (error) {
    mergeStudents.value = []
    ui.notify(error.message)
  } finally {
    loading.value = false
  }
}

async function toggleMergeJsonFile(fileName) {
  editorMode.value = 'merge'
  selectedFile.value = ''
  selectedJsonFile.value = ''
  sourceStudents.value = []
  resetSelection()
  mergeSelectedJsonFiles.value = mergeSelectedJsonFiles.value.includes(fileName)
    ? mergeSelectedJsonFiles.value.filter((value) => value !== fileName)
    : [...mergeSelectedJsonFiles.value, fileName]
  await rebuildMergePreview()
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

function normalizeJsonFileName(name) {
  return safeListName([String(name).replace(/\.json$/i, '').trim()])
}

async function renameJson(fileName) {
  const currentName = displayFileName(fileName)
  const input = window.prompt('Nieuwe naam voor deze JSON-lijst:', currentName)
  if (input == null) return
  const newName = normalizeJsonFileName(input)
  if (!newName || newName === fileName) return
  if (
    jsonFiles.value.some((file) => file.name === newName) &&
    !window.confirm(`${newName} bestaat al. Wil je dit bestand overschrijven?`)
  ) {
    return
  }

  loading.value = true
  try {
    const list = await api.getList(fileName)
    const students = normalizeJson(list.content)
    await api.saveList(newName, students, {
      listType: list.content.listType || 'mentor',
      selection: Array.isArray(list.content.selection)
        ? list.content.selection
        : [displayFileName(newName)],
    })
    await api.deleteList(fileName)
    if (selectedJsonFile.value === fileName) selectedJsonFile.value = newName
    mergeSelectedJsonFiles.value = mergeSelectedJsonFiles.value.map((value) =>
      value === fileName ? newName : value,
    )
    await loadFiles()
    if (editorMode.value === 'merge') await rebuildMergePreview()
    ui.notify(`${fileName} hernoemd naar ${newName}.`)
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

function studentKey(student) {
  return student.studentNumber || `${student.fullName}-${student.position}`
}

function resetNewStudent() {
  newStudent.value = {
    studentNumber: '',
    fullName: '',
    firstName: '',
    lastName: '',
    mentor: '',
    group: '',
    cumLaude: false,
  }
}

function openAddStudentModal() {
  resetNewStudent()
  addStudentModalOpen.value = true
}

function closeAddStudentModal() {
  addStudentModalOpen.value = false
  resetNewStudent()
}

function addStudentToJson() {
  const studentNumber = newStudent.value.studentNumber.trim()
  const fullName = newStudent.value.fullName.trim()
  if (!studentNumber || !fullName) {
    ui.notify('Leerlingnummer en volledige naam zijn verplicht.')
    return
  }
  const student = {
    position: listStudents.value.length + 1,
    studentNumber,
    fullName,
    firstName: newStudent.value.firstName.trim(),
    lastName: newStudent.value.lastName.trim(),
    mentor: newStudent.value.mentor.trim(),
    group: newStudent.value.group.trim(),
    cumLaude: Boolean(newStudent.value.cumLaude),
  }
  listStudents.value = renumber([...listStudents.value, student])
  newlyAddedStudentKeys.value = new Set([...newlyAddedStudentKeys.value, studentKey(student)])
  search.value = ''
  closeAddStudentModal()
  ui.notify(`${student.fullName} onderaan toegevoegd.`)
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

async function saveMergedJson() {
  if (!canSaveMerged.value) return
  saving.value = true
  try {
    await api.saveList(mergeProposedName.value, mergeStudents.value, {
      listType: 'combined-json',
      selection: mergeSelectedJsonFiles.value.map((fileName) => displayFileName(fileName)),
    })
    await loadFiles()
    selectedJsonFile.value = mergeProposedName.value
    ui.notify(`${mergeProposedName.value} opgeslagen met ${mergeStudents.value.length} leerlingen.`)
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
            Maak een nieuwe lijst vanuit een CSV, open een bestaande JSON-presentatielijst, of voeg meerdere JSONs samen.
          </p>
          <div class="mt-4 grid max-w-3xl grid-cols-1 gap-2 sm:grid-cols-3">
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
            <button
              class="rounded-lg border p-3 text-center font-semibold"
              :class="editorMode === 'merge' ? 'border-gold-dark bg-gold text-ink' : 'border-slate-300'"
              type="button"
              @click="switchMode('merge')"
            >
              JSON samenvoegen
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
            class="relative rounded-2xl border bg-white p-4 pb-12 text-left text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg disabled:cursor-wait"
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
                <button
                  class="file-card-icon-button file-card-icon-button-danger"
                  type="button"
                  :disabled="loading"
                  :aria-label="`${file.name} verwijderen`"
                  @click.stop="deleteCsv(file.name)"
                >
                  <Trash2 :size="16" />
                </button>
              </span>
            </div>
            <span class="absolute bottom-3 right-3 rounded-full bg-navy px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">
              .csv
            </span>
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

        <div v-else-if="editorMode === 'json'" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
          <article
            v-for="file in jsonFiles"
            :key="file.name"
            class="relative rounded-2xl border bg-white p-4 pb-12 text-left text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
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
                <button
                  class="file-card-icon-button file-card-icon-button-rename"
                  type="button"
                  :disabled="loading"
                  :aria-label="`${file.name} hernoemen`"
                  @click.stop="renameJson(file.name)"
                >
                  <span class="file-card-icon-label">Hernoemen</span>
                  <Pencil :size="16" />
                </button>
                <button
                  class="file-card-icon-button file-card-icon-button-danger"
                  type="button"
                  :disabled="loading"
                  :aria-label="`${file.name} verwijderen`"
                  @click.stop="deleteJson(file.name)"
                >
                  <Trash2 :size="16" />
                </button>
              </span>
            </div>
            <span class="absolute bottom-3 right-3 rounded-full bg-navy px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">
              .json
            </span>
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

        <div v-else class="space-y-5">
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <article
              v-for="file in jsonFiles"
              :key="file.name"
              class="relative rounded-2xl border bg-white p-4 pb-12 text-left text-ink shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
              :class="
                mergeSelectedJsonFiles.includes(file.name)
                  ? 'border-navy ring-4 ring-navy/20'
                  : 'border-slate-200 hover:border-navy/40'
              "
              @click="toggleMergeJsonFile(file.name)"
            >
              <span
                v-if="mergeSelectedJsonFiles.includes(file.name)"
                class="absolute -right-2 -top-2 grid h-8 w-8 place-items-center rounded-full bg-navy text-sm font-black text-white shadow"
              >
                {{ mergeSelectedJsonFiles.indexOf(file.name) + 1 }}
              </span>
              <div class="mb-3 flex items-start justify-between gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-gold/50 text-navy">
                  <FileJson :size="28" />
                </span>
                <span class="flex items-center gap-1">
                  <button
                    class="file-card-icon-button file-card-icon-button-rename"
                    type="button"
                    :disabled="loading"
                    :aria-label="`${file.name} hernoemen`"
                    @click.stop="renameJson(file.name)"
                  >
                    <span class="file-card-icon-label">Hernoemen</span>
                    <Pencil :size="16" />
                  </button>
                  <button
                    class="file-card-icon-button file-card-icon-button-danger"
                    type="button"
                    :disabled="loading"
                    :aria-label="`${file.name} verwijderen`"
                    @click.stop="deleteJson(file.name)"
                  >
                    <Trash2 :size="16" />
                  </button>
                </span>
              </div>
              <span class="absolute bottom-3 right-3 rounded-full bg-navy px-2.5 py-1 text-xs font-black uppercase tracking-wide text-white">
                .json
              </span>
              <p class="break-words font-bold leading-tight">{{ displayFileName(file.name) }}</p>
              <p class="muted mt-2">
                {{
                  Number.isFinite(file.studentCount)
                    ? `${file.studentCount} leerlingen`
                    : 'Aantal leerlingen onbekend'
                }}
              </p>
            </article>
          </div>

          <div v-if="mergeSelectedJsonFiles.length" class="rounded-2xl border border-white/60 bg-white/70 p-4 text-ink shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 class="text-lg font-bold">Samenvoegvolgorde</h2>
                <p class="muted mt-1">
                  {{ mergeStudents.length }} leerlingen · wordt opgeslagen als {{ mergeProposedName }}
                </p>
              </div>
              <button
                class="button-primary"
                :disabled="saving || !canSaveMerged"
                @click="saveMergedJson"
              >
                <UploadCloud :size="18" />
                {{ saving ? 'Opslaan…' : `${mergeProposedName} opslaan` }}
              </button>
            </div>
            <ol class="mt-4 grid gap-2 md:grid-cols-2">
              <li
                v-for="(fileName, index) in mergeSelectedJsonFiles"
                :key="fileName"
                class="flex items-center gap-3 rounded-xl bg-white px-3 py-2 shadow-sm"
              >
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-navy text-sm font-black text-white">
                  {{ index + 1 }}
                </span>
                <span class="font-semibold">{{ displayFileName(fileName) }}</span>
              </li>
            </ol>
            <p v-if="mergeSelectedJsonFiles.length < 2" class="mt-3 text-sm text-amber-700">
              Kies minimaal twee JSON-bestanden om samen te voegen.
            </p>
          </div>
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

        </div>

        <div class="flex flex-wrap gap-3">
          <button class="button-secondary" @click="sortByLastName">
            <ArrowDownAZ :size="18" /> Sorteer op achternaam
          </button>
          <button class="button-secondary" @click="shuffle"><Shuffle :size="18" /> Schudden</button>
          <button
            v-if="editorMode === 'json'"
            class="button-secondary"
            type="button"
            @click="openAddStudentModal"
          >
            <Plus :size="18" /> Leerling toevoegen
          </button>
          <button class="button-secondary" @click="downloadList">
            <Download :size="18" /> Download
          </button>
                    <label class="relative min-w-64 flex-1 lg:max-w-sm">
            <Search class="absolute left-3 top-2.5 text-slate-500" :size="20" />
            <input v-model="search" class="field pl-10" placeholder="Zoek binnen deze lijst…" />
          </label>
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
                :class="newlyAddedStudentKeys.has(studentKey(student)) ? 'bg-gold/20' : ''"
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
                <td class="p-3 font-medium">
                  <span class="inline-flex items-center gap-2">
                    {{ student.firstName || student.fullName }}
                    <span
                      v-if="newlyAddedStudentKeys.has(studentKey(student))"
                      class="inline-flex items-center gap-1 rounded-full bg-gold px-2 py-1 text-[0.65rem] font-black uppercase tracking-wide text-ink"
                    >
                      <Sparkles :size="13" />
                      Nieuw
                    </span>
                  </span>
                </td>
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
                :class="newlyAddedStudentKeys.has(studentKey(student)) ? 'bg-gold/20' : ''"
              >
                <td class="p-3">{{ student.position }}</td>
                <td class="p-3">
                  <PhotoPair :student-number="student.studentNumber" :photo-base-url="config.photoBaseUrl" />
                </td>
                <td class="p-3">{{ student.studentNumber }}</td>
                <td class="p-3 font-medium">
                  <span class="inline-flex items-center gap-2">
                    {{ student.firstName || student.fullName }}
                    <span
                      v-if="newlyAddedStudentKeys.has(studentKey(student))"
                      class="inline-flex items-center gap-1 rounded-full bg-gold px-2 py-1 text-[0.65rem] font-black uppercase tracking-wide text-ink"
                    >
                      <Sparkles :size="13" />
                      Nieuw
                    </span>
                  </span>
                </td>
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

    <Teleport to="body">
      <div
        v-if="addStudentModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        @click.self="closeAddStudentModal"
      >
        <section
          class="w-full max-w-2xl rounded-3xl bg-white p-6 text-ink shadow-2xl"
          @click.stop
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-bold uppercase tracking-wide text-navy">JSON bewerken</p>
              <h2 class="mt-1 text-2xl font-bold">Leerling toevoegen</h2>
              <p class="muted mt-1">De leerling wordt onderaan de huidige lijst toegevoegd.</p>
            </div>
            <button
              type="button"
              class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
              aria-label="Leerling toevoegen sluiten"
              @click="closeAddStudentModal"
            >
              <X :size="24" />
            </button>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <label class="block">
              <span class="mb-1 block font-semibold">Leerlingnummer *</span>
              <input v-model.trim="newStudent.studentNumber" class="field" />
            </label>
            <label class="block">
              <span class="mb-1 block font-semibold">Volledige naam *</span>
              <input v-model.trim="newStudent.fullName" class="field" />
            </label>
            <label class="block">
              <span class="mb-1 block font-semibold">Voornaam</span>
              <input v-model.trim="newStudent.firstName" class="field" />
            </label>
            <label class="block">
              <span class="mb-1 block font-semibold">Achternaam</span>
              <input v-model.trim="newStudent.lastName" class="field" />
            </label>
            <label class="block">
              <span class="mb-1 block font-semibold">Mentor</span>
              <input v-model.trim="newStudent.mentor" class="field" />
            </label>
            <label class="block">
              <span class="mb-1 block font-semibold">Stamgroep</span>
              <input v-model.trim="newStudent.group" class="field" />
            </label>
            <label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 p-3 font-semibold md:col-span-2">
              <input v-model="newStudent.cumLaude" type="checkbox" class="h-5 w-5 accent-navy" />
              Cum laude
            </label>
          </div>

          <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
            <button type="button" class="button-secondary border-slate-300 text-slate-700" @click="closeAddStudentModal">
              Annuleren
            </button>
            <button type="button" class="button-secondary border-navy text-navy" @click="addStudentToJson">
              <Plus :size="18" />
              Toevoegen
            </button>
          </div>
        </section>
      </div>
    </Teleport>
  </main>
</template>
