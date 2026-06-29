<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'
import { Image, Save, Trash2, UploadCloud, X } from 'lucide-vue-next'
import AppHeader from '../components/AppHeader.vue'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'

const ui = useUiStore()
const config = ref({
  importBaseUrl: 'storage/imports',
  listBaseUrl: 'storage/lists',
  photoBaseUrl: 'storage/photos',
  defaultTitle: '',
})
const photos = ref([])
const busy = ref(false)
const uploadInputs = ref({})
const cropper = ref(null)
const cropModal = ref({
  department: '',
  fileName: '',
  imageUrl: '',
})
const fallback = `${import.meta.env.BASE_URL}images/no-photo.jpg`
const maxCropWidth = 1920
const maxCropHeight = 1080

const photoByDepartment = computed(() =>
  Object.fromEntries(photos.value.map((photo) => [photo.department, photo])),
)

function groupPhotoUrl(department) {
  return `${import.meta.env.BASE_URL}${config.value.photoBaseUrl}/examenfoto_${department}.jpg`
}

async function load() {
  try {
    ;[config.value, photos.value] = await Promise.all([
      api.getConfig(),
      api.getGroupPhotos(),
    ])
  } catch (error) {
    ui.notify(error.message)
  }
}

async function saveConfig() {
  busy.value = true
  try {
    config.value = await api.updateConfig(config.value)
    ui.notify('Beheerinstellingen opgeslagen.')
  } catch (error) {
    ui.notify(error.message)
  } finally {
    busy.value = false
  }
}

function closeCropModal() {
  if (cropModal.value.imageUrl) {
    URL.revokeObjectURL(cropModal.value.imageUrl)
  }
  cropModal.value = {
    department: '',
    fileName: '',
    imageUrl: '',
  }
}

function openCropModal(department, event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  if (!['image/jpeg', 'image/pjpeg'].includes(file.type)) {
    ui.notify('Alleen JPG-groepsfoto’s zijn toegestaan.')
    return
  }

  closeCropModal()
  cropModal.value = {
    department,
    fileName: file.name,
    imageUrl: URL.createObjectURL(file),
  }
}

function canvasToJpegBlob(canvas) {
  const scale = Math.min(1, maxCropWidth / canvas.width, maxCropHeight / canvas.height)
  const targetCanvas = document.createElement('canvas')
  targetCanvas.width = Math.max(1, Math.round(canvas.width * scale))
  targetCanvas.height = Math.max(1, Math.round(canvas.height * scale))
  const context = targetCanvas.getContext('2d')
  context.drawImage(canvas, 0, 0, targetCanvas.width, targetCanvas.height)

  return new Promise((resolve, reject) => {
    targetCanvas.toBlob(
      (blob) => {
        if (blob) resolve(blob)
        else reject(new Error('De uitgesneden foto kon niet worden verwerkt.'))
      },
      'image/jpeg',
      0.9,
    )
  })
}

async function saveCroppedPhoto() {
  if (!cropModal.value.department || !cropper.value) return
  busy.value = true
  try {
    const result = cropper.value.getResult()
    if (!result?.canvas) {
      throw new Error('Selecteer eerst een uitsnede.')
    }
    const blob = await canvasToJpegBlob(result.canvas)
    const file = new File(
      [blob],
      `examenfoto_${cropModal.value.department}.jpg`,
      { type: 'image/jpeg' },
    )
    await api.uploadGroupPhoto(cropModal.value.department, file)
    photos.value = await api.getGroupPhotos()
    ui.notify(`Groepsfoto voor ${cropModal.value.department.toUpperCase()} opgeslagen.`)
    closeCropModal()
  } catch (error) {
    ui.notify(error.message)
  } finally {
    busy.value = false
  }
}

async function deletePhoto(department) {
  busy.value = true
  try {
    await api.deleteGroupPhoto(department)
    photos.value = await api.getGroupPhotos()
    ui.notify(`Groepsfoto voor ${department.toUpperCase()} verwijderd.`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    busy.value = false
  }
}

onMounted(load)
onBeforeUnmount(closeCropModal)
</script>

<template>
  <main class="page-shell">
    <AppHeader compact />
    <div class="content-width space-y-6">
      <section class="panel">
        <p class="text-sm font-bold uppercase tracking-wide text-navy">Beheer</p>
        <h1 class="mt-1 text-2xl font-bold">Algemene instellingen</h1>
        <label class="mt-5 block max-w-2xl">
          <span class="mb-1 block font-semibold">Default titel op de titelpagina</span>
          <input v-model.trim="config.defaultTitle" class="field" placeholder="Diplomauitreiking 2026" />
        </label>
        <button class="button-primary mt-4" :disabled="busy" @click="saveConfig">
          <Save :size="18" />
          Instellingen opslaan
        </button>
      </section>

      <section class="panel">
        <div class="mb-5">
          <p class="text-sm font-bold uppercase tracking-wide text-navy">Leerlingfoto’s</p>
          <h2 class="mt-1 text-xl font-bold">Instructie voor leerlingfoto's</h2>
          <p class="muted mt-1">Plaats de leerlingfoto's in format <code>123456_1.jpg</code> en <code>123456_2.jpg</code> in de map <code>storage/photos/</code>. 
            Als DiploPresent een foto niet vindt, wordt er teruggevallen op een default placeholder.</p>
        </div>
      </section>

      <section class="panel">
        <div class="mb-5">
          <p class="text-sm font-bold uppercase tracking-wide text-navy">Groepsfoto’s</p>
          <h2 class="mt-1 text-xl font-bold">Beheer titelpaginafoto per afdeling</h2>
          <p class="muted mt-1">
            Upload JPG-bestanden. Ze worden opgeslagen als
            <code>examenfoto_havo.jpg</code> en <code>examenfoto_vwo.jpg</code> in de map <code>storage/photos/</code>.
          </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <article
            v-for="department in ['havo', 'vwo']"
            :key="department"
            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-lg font-bold uppercase">{{ department }}</h3>
                <p class="muted">
                  {{
                    photoByDepartment[department]?.exists
                      ? `${photoByDepartment[department].name}`
                      : 'Nog geen groepsfoto'
                  }}
                </p>
              </div>
              <Image class="text-navy" :size="34" />
            </div>

            <img
              :src="groupPhotoUrl(department)"
              :alt="`Groepsfoto ${department}`"
              class="mt-4 aspect-video w-full rounded-xl bg-slate-200 object-cover"
              @error="($event.target.src = fallback)"
            />

            <input
              :ref="(element) => { if (element) uploadInputs[department] = element }"
              class="hidden"
              type="file"
              accept=".jpg,.jpeg,image/jpeg"
              @change="openCropModal(department, $event)"
            />

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
              <button
                class="button-primary"
                :disabled="busy"
                @click="uploadInputs[department]?.click()"
              >
                <UploadCloud :size="18" />
                Uploaden
              </button>
              <button
                class="button-secondary border-red-300 text-red-700 hover:bg-red-100"
                :disabled="busy || !photoByDepartment[department]?.exists"
                @click="deletePhoto(department)"
              >
                <Trash2 :size="18" />
                Verwijderen
              </button>
            </div>
          </article>
        </div>
      </section>
    </div>

    <Teleport to="body">
      <div
        v-if="cropModal.imageUrl"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
      >
        <section class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
          <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
            <div>
              <p class="text-sm font-bold uppercase tracking-wide text-navy">Groepsfoto bijsnijden</p>
              <h2 class="mt-1 text-2xl font-bold">
                {{ cropModal.department.toUpperCase() }}
              </h2>
              <p class="muted mt-1">
                Sleep en zoom de uitsnede. De foto wordt maximaal {{ maxCropWidth }}×{{ maxCropHeight }} opgeslagen.
              </p>
            </div>
            <button
              type="button"
              class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
              :disabled="busy"
              aria-label="Bijsnijden annuleren"
              @click="closeCropModal"
            >
              <X :size="24" />
            </button>
          </header>

          <div class="min-h-0 flex-1 bg-slate-950 p-4">
            <Cropper
              ref="cropper"
              class="h-[62vh] max-h-[620px] rounded-2xl bg-slate-900"
              :src="cropModal.imageUrl"
              :stencil-props="{ movable: true, resizable: true }"
              image-restriction="stencil"
            />
          </div>

          <footer class="flex flex-col gap-3 border-t border-slate-200 p-5 sm:flex-row sm:justify-end">
            <button
              type="button"
              class="button-secondary border-slate-300 text-slate-700"
              :disabled="busy"
              @click="closeCropModal"
            >
              Annuleren
            </button>
            <button
              type="button"
              class="button-primary"
              :disabled="busy"
              @click="saveCroppedPhoto"
            >
              <UploadCloud :size="18" />
              {{ busy ? 'Opslaan…' : 'Opslaan' }}
            </button>
          </footer>
        </section>
      </div>
    </Teleport>
  </main>
</template>
