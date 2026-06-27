<script setup>
import { computed, onMounted, ref } from 'vue'
import { Image, Save, Trash2, UploadCloud } from 'lucide-vue-next'
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
const fallback = `${import.meta.env.BASE_URL}images/no-photo.jpg`

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
    ui.notify('Admin-instellingen opgeslagen.')
  } catch (error) {
    ui.notify(error.message)
  } finally {
    busy.value = false
  }
}

async function uploadPhoto(department, event) {
  const file = event.target.files?.[0]
  if (!file) return
  busy.value = true
  try {
    await api.uploadGroupPhoto(department, file)
    photos.value = await api.getGroupPhotos()
    ui.notify(`Groepsfoto voor ${department.toUpperCase()} opgeslagen.`)
  } catch (error) {
    ui.notify(error.message)
  } finally {
    event.target.value = ''
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
</script>

<template>
  <main class="page-shell">
    <AppHeader compact />
    <div class="content-width space-y-6">
      <section class="panel">
        <p class="text-sm font-bold uppercase tracking-wide text-navy">Admin</p>
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
              @change="uploadPhoto(department, $event)"
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
  </main>
</template>
