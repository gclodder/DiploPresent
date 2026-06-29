<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import confetti from 'canvas-confetti'
import { GraduationCap, Maximize, Minimize } from 'lucide-vue-next'
import { useRoute } from 'vue-router'
import { api } from '../api/client'
import { normalizeJson, photoUrl } from '../domain/students'
import { usePresentationStore } from '../stores/presentation'

const route = useRoute()
const presentation = usePresentationStore()
const index = ref(-1)
const displayedIndex = ref(-1)
const phase = ref('start')
const peekVisible = ref(false)
const fullscreen = ref(false)
const controlsVisible = ref(true)
const testPatternVisible = ref(false)
const cardMotion = ref(createCardMotion())
const timers = new Set()
const preloadedImages = new Set()
let transitionRunId = 0
let controlsHideTimer = null
const sessionPoller = ref(null)
const sharedSession = ref(null)
const sharedRevision = ref(0)
const fallback = `${import.meta.env.BASE_URL}images/no-photo.jpg`
const schoolLogo = `${import.meta.env.BASE_URL}images/SP-logo.png`

const current = computed(() => presentation.students[displayedIndex.value] ?? null)
const nextStudent = computed(() => presentation.students[index.value + 1] ?? null)
const counter = computed(() => `${index.value < 0 ? '-' : index.value + 1}/${presentation.students.length}`)
const groupPhoto = computed(
  () =>
    `${import.meta.env.BASE_URL}${presentation.photoBaseUrl}/examenfoto_${presentation.department}_1920.jpg`,
)
const isShared = computed(() => Boolean(route.query.session))
const oldPhoto = computed(() =>
  current.value ? photoUrl(presentation.photoBaseUrl, current.value.studentNumber, 1) : '',
)
const newPhoto = computed(() =>
  current.value ? photoUrl(presentation.photoBaseUrl, current.value.studentNumber, 2) : '',
)
const oldCardStyle = computed(() => cardStyle('old'))
const newCardStyle = computed(() => cardStyle('new'))

function randomBetween(min, max) {
  return min + Math.random() * (max - min)
}

function pick(values) {
  return values[Math.floor(Math.random() * values.length)]
}

function randomRotation(minAbs, maxAbs) {
  const direction = Math.random() < 0.5 ? -1 : 1
  return randomBetween(minAbs, maxAbs) * direction
}

function createCardMotion() {
  const easing = pick([
    'cubic-bezier(.18,.88,.24,1.08)',
    'cubic-bezier(.16,1,.3,1)',
    'cubic-bezier(.22,.72,.18,1.03)',
    'cubic-bezier(.2,.9,.2,1)',
  ])
  const oldStartRotation = randomRotation(8, 22)
  const oldEndRotation = randomRotation(0.5, 8)
  const newStartRotation = randomBetween(8, 22) * -Math.sign(oldStartRotation)
  const newEndRotation = randomBetween(0.5, 8) * -Math.sign(oldEndRotation)
  return {
    old: {
      hidden: `translate(${randomBetween(-175, -135)}vw, ${randomBetween(-18, 14)}vh) rotate(${oldStartRotation}deg) scale(0.8)`,
      visible: `translate(${randomBetween(-16, 18)}px, ${randomBetween(-10, 12)}px) rotate(${oldEndRotation}deg) scale(0.8)`,
      duration: Math.round(randomBetween(760, 1040)),
      easing,
    },
    new: {
      hidden: `translate(${randomBetween(135, 175)}vw, ${randomBetween(-14, 18)}vh) rotate(${newStartRotation}deg) scale(1)`,
      visible: `translate(${randomBetween(-18, 16)}px, ${randomBetween(-12, 10)}px) rotate(${newEndRotation}deg) scale(1)`,
      duration: Math.round(randomBetween(820, 1120)),
      easing: pick([easing, 'cubic-bezier(.14,.86,.22,1.12)', 'cubic-bezier(.2,.95,.18,1)']),
    },
  }
}

function cardStyle(card) {
  const motion = cardMotion.value[card]
  const oldVisible = card === 'old' && ['old', 'new', 'named'].includes(phase.value)
  const newVisible = card === 'new' && ['new', 'named'].includes(phase.value)
  const visible = oldVisible || newVisible
  const duration = phase.value === 'leaving' ? Math.round(motion.duration * 1.35) : motion.duration
  return {
    opacity: visible ? 1 : 0,
    transform: visible ? motion.visible : motion.hidden,
    transition: `transform ${duration}ms ${motion.easing}, opacity ${Math.round(duration * 0.75)}ms ease-out`,
    willChange: 'transform, opacity',
  }
}

function currentLeaveDuration() {
  return Math.round(Math.max(cardMotion.value.old.duration, cardMotion.value.new.duration) * 1.35)
}

function newPhotoUrl(student) {
  return photoUrl(presentation.photoBaseUrl, student.studentNumber, 2)
}

function preloadImage(src) {
  if (!src || preloadedImages.has(src)) return Promise.resolve()
  return new Promise((resolve) => {
    const image = new window.Image()
    image.onload = () => {
      preloadedImages.add(src)
      resolve()
    }
    image.onerror = () => resolve()
    image.src = src
  })
}

function preloadStudentPhotos(student) {
  return Promise.all([
    preloadImage(photoUrl(presentation.photoBaseUrl, student.studentNumber, 1)),
    preloadImage(photoUrl(presentation.photoBaseUrl, student.studentNumber, 2)),
  ])
}

function later(callback, delay) {
  const timer = window.setTimeout(() => {
    timers.delete(timer)
    callback()
  }, delay)
  timers.add(timer)
}

function clearTimers() {
  timers.forEach((timer) => window.clearTimeout(timer))
  timers.clear()
}

function hideControlsLater() {
  if (controlsHideTimer) window.clearTimeout(controlsHideTimer)
  controlsHideTimer = window.setTimeout(() => {
    controlsVisible.value = false
  }, 3000)
}

function revealControls() {
  controlsVisible.value = true
  hideControlsLater()
}

function fireConfetti() {
  const colors = ['#d6bf92', '#ffffff']
  confetti({ particleCount: 130, spread: 90, origin: { y: 0.7 }, colors })
  confetti({ particleCount: 40, angle: 60, spread: 70, origin: { x: 0 }, colors })
  confetti({ particleCount: 40, angle: 120, spread: 70, origin: { x: 1 }, colors })
  if (current.value?.cumLaude) {
    confetti({
      particleCount: 90,
      spread: 65,
      startVelocity: 35,
      origin: { y: 0.52 },
      colors: ['#f8e7a1', '#d6bf92', '#ffffff'],
    })
  }
}

async function showStudent(nextIndex) {
  const runId = ++transitionRunId
  if (!isShared.value) testPatternVisible.value = false
  if (nextIndex < 0) {
    clearTimers()
    index.value = -1
    if (displayedIndex.value < 0) {
      phase.value = 'start'
      return
    }
    phase.value = 'leaving'
    later(() => {
      displayedIndex.value = -1
      phase.value = 'start'
    }, 700)
    return
  }
  if (nextIndex >= presentation.students.length) return

  clearTimers()
  const targetStudent = presentation.students[nextIndex]
  const hadVisibleStudent = displayedIndex.value >= 0 && phase.value !== 'start'
  index.value = nextIndex
  if (hadVisibleStudent) {
    phase.value = 'leaving'
    await Promise.all([
      preloadStudentPhotos(targetStudent),
      new Promise((resolve) => later(resolve, currentLeaveDuration())),
    ])
    if (runId !== transitionRunId) return
    cardMotion.value = createCardMotion()
    displayedIndex.value = nextIndex
    phase.value = 'old'
  } else {
    await preloadStudentPhotos(targetStudent)
    if (runId !== transitionRunId) return
    cardMotion.value = createCardMotion()
    displayedIndex.value = nextIndex
    await nextTick()
    phase.value = 'old'
  }
  later(() => {
    if (runId === transitionRunId) phase.value = 'new'
  }, 700)
  later(() => {
    if (runId === transitionRunId) phase.value = 'named'
  }, 1400)
  later(() => {
    if (runId === transitionRunId) fireConfetti()
  }, 1750)
}

function jumpToStudent(nextIndex) {
  if (isShared.value) return
  showStudent(nextIndex)
}

function toggleTestPattern() {
  testPatternVisible.value = !testPatternVisible.value
}

function onKeydown(event) {
  if (event.key === 'ArrowRight') showStudent(index.value + 1)
  if (event.key === 'ArrowLeft') showStudent(index.value - 1)
  if (event.key === '0') showStudent(-1)
  if (event.key.toLowerCase() === 'v') peekVisible.value = true
  if (event.key.toLowerCase() === 't' && !event.repeat) toggleTestPattern()
  if (event.altKey && /^[1-9]$/.test(event.key)) showStudent(Number(event.key) - 1)
}

function onKeyup(event) {
  if (event.key.toLowerCase() === 'v') peekVisible.value = false
}

async function toggleFullscreen() {
  if (!document.fullscreenElement) {
    await document.documentElement.requestFullscreen()
  } else {
    await document.exitFullscreen()
  }
}

function onFullscreenChange() {
  fullscreen.value = Boolean(document.fullscreenElement)
}

onMounted(async () => {
  presentation.restore()
  const sharedId = route.query.session ? String(route.query.session) : ''
  const listName = route.params.listName ? decodeURIComponent(route.params.listName) : ''
  if (sharedId) {
    try {
      sharedSession.value = await api.getSession(sharedId)
      const [list, config] = await Promise.all([
        api.getList(sharedSession.value.listName),
        api.getConfig(),
      ])
      presentation.listName = sharedSession.value.listName
      presentation.students = normalizeJson(list.content)
      presentation.photoBaseUrl = config.photoBaseUrl
      presentation.title = sharedSession.value.title
      presentation.department = sharedSession.value.department
      sharedRevision.value = sharedSession.value.revision
      index.value = sharedSession.value.index
      displayedIndex.value = sharedSession.value.index
      phase.value = index.value < 0 ? 'start' : 'named'
      peekVisible.value = sharedSession.value.peek
      testPatternVisible.value = Boolean(sharedSession.value.testPattern)
    } catch {
      // Het startscherm toont hieronder dat er geen leerlingen beschikbaar zijn.
    }
    sessionPoller.value = window.setInterval(async () => {
      try {
        const latest = await api.getSession(sharedId)
        peekVisible.value = latest.peek
        testPatternVisible.value = Boolean(latest.testPattern)
        if (latest.revision !== sharedRevision.value) {
          sharedRevision.value = latest.revision
          sharedSession.value = latest
          if (latest.index !== index.value) await showStudent(latest.index)
        }
      } catch {
        // Houd het laatst bekende beeld vast bij een tijdelijke netwerkstoring.
      }
    }, 600)
  } else if ((!presentation.students.length || presentation.listName !== listName) && listName) {
    try {
      const [list, config] = await Promise.all([api.getList(listName), api.getConfig()])
      presentation.listName = listName
      presentation.students = normalizeJson(list.content)
      presentation.photoBaseUrl = config.photoBaseUrl
      presentation.persist()
    } catch {
      // Het startscherm toont hieronder dat er geen leerlingen beschikbaar zijn.
    }
  }
  document.title = presentation.title || 'DiploPresent'
  if (!isShared.value) {
    window.addEventListener('keydown', onKeydown)
    window.addEventListener('keyup', onKeyup)
  }
  window.addEventListener('mousemove', revealControls)
  window.addEventListener('touchstart', revealControls)
  document.addEventListener('fullscreenchange', onFullscreenChange)
  hideControlsLater()
})

onBeforeUnmount(() => {
  clearTimers()
  if (controlsHideTimer) window.clearTimeout(controlsHideTimer)
  if (sessionPoller.value) window.clearInterval(sessionPoller.value)
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('keyup', onKeyup)
  window.removeEventListener('mousemove', revealControls)
  window.removeEventListener('touchstart', revealControls)
  document.removeEventListener('fullscreenchange', onFullscreenChange)
})
</script>

<template>
  <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[linear-gradient(90deg,#030c20_0%,#204568_50%,#030c20_100%)] p-8">
    <section v-if="phase === 'start'" class="relative flex w-full max-w-6xl flex-col items-center gap-6 text-center">
      <h1 v-if="presentation.title" class="text-4xl font-bold text-white drop-shadow-lg md:text-5xl">
        {{ presentation.title }}
      </h1>
      <img
        v-if="presentation.students.length"
        :src="groupPhoto"
        alt="Groepsfoto"
        class="max-h-[72vh] w-full rounded-3xl object-contain shadow-2xl"
        @error="($event.target.src = fallback)"
      />
      <div v-else class="panel mx-auto max-w-xl">
        <h1 class="text-2xl font-bold">Geen presentatie geladen</h1>
        <p class="mt-2 text-slate-600">Open deze presentatie opnieuw vanuit de Presenter.</p>
      </div>
    </section>

    <section v-else-if="current" class="grid w-full max-w-7xl grid-cols-[1fr_auto_1fr] items-center gap-8">
      <article
        :style="oldCardStyle"
      >
        <div class="bg-white p-4 pb-8 text-ink shadow-2xl">
          <img
            :src="oldPhoto"
            alt="Oude leerlingfoto"
            class="aspect-[4/5] w-full object-cover"
            @error="($event.target.src = fallback)"
          />
          <p class="pt-4 text-center text-2xl">brugklas / instroom</p>
        </div>
      </article>

      <div
        class="w-[28rem] text-center transition duration-500"
        :class="phase === 'named' ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
      >
        <h2 class="text-5xl font-bold">{{ current.fullName }}</h2>
        <div
          v-if="current.cumLaude"
          class="cum-laude-badge relative mx-auto mt-6 inline-flex overflow-hidden rounded-full border border-gold-dark bg-gold px-6 py-3 text-2xl font-bold text-ink shadow-[0_0_38px_rgba(214,191,146,0.45)]"
        >
          <span class="relative z-10 inline-flex items-center gap-3">
            <GraduationCap :size="30" />
            Cum laude
          </span>
        </div>
      </div>

      <article
        :style="newCardStyle"
      >
        <div class="bg-white p-4 pb-8 text-ink shadow-2xl">
          <img
            :src="newPhoto"
            alt="Nieuwe leerlingfoto"
            class="aspect-[4/5] w-full object-cover"
            @error="($event.target.src = fallback)"
          />
          <p class="pt-4 text-center text-2xl">examenjaar</p>
        </div>
      </article>
    </section>

    <img :src="schoolLogo" alt="Spieringshoek" class="fixed bottom-5 left-1/2 w-80 -translate-x-1/2 opacity-80" />
    <div class="group fixed bottom-0 left-0 z-30 p-4">
      <div
        class="live-jump-list pointer-events-none absolute bottom-[calc(100%-1rem)] left-4 mb-3 max-h-[55vh] w-80 overflow-y-auto rounded-2xl border border-white/15 bg-slate-950/65 p-2 opacity-0 shadow-2xl backdrop-blur-md transition-opacity duration-150 group-hover:pointer-events-auto group-hover:opacity-100"
      >
        <p class="sticky top-0 z-10 -mx-2 -mt-2 mb-4 rounded-2xl border-b border-white/10  px-5 pb-2 pt-3 text-xs font-bold uppercase tracking-wide text-white/55 backdrop-blur-md">
          Snel springen
        </p>
        <button
          type="button"
          class="mb-1 flex w-full items-center gap-3 rounded-xl bg-white/10 px-3 py-2 text-left text-white transition hover:bg-white/20 disabled:cursor-default disabled:opacity-50"
          :disabled="isShared"
          @click="jumpToStudent(-1)"
        >
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white/10 text-lg">↺</span>
          <span>
            <span class="block font-semibold">Titelpagina</span>
            <span v-if="isShared" class="block text-xs text-white/50">Bediening via dashboard</span>
          </span>
        </button>
        <button
          v-for="(student, studentIndex) in presentation.students"
          :key="student.studentNumber || `${student.fullName}-${studentIndex}`"
          type="button"
          class="cursor-pointer mb-1 flex w-full items-center gap-3 rounded-xl bg-white/10 px-3 py-2 text-left text-white transition hover:bg-white/20 disabled:cursor-default disabled:opacity-50"
          :class="index === studentIndex ? 'ring-1 ring-gold/70' : ''"
          :disabled="isShared"
          @click="jumpToStudent(studentIndex)"
        >
          <img
            :src="newPhotoUrl(student)"
            :alt="student.fullName"
            class="h-10 w-10 shrink-0 rounded-lg object-cover"
            @error="($event.target.src = fallback)"
          />
          <span class="min-w-0">
            <span class="block truncate font-semibold">{{ student.fullName }}</span>
            <span class="block text-xs text-white/50">{{ studentIndex + 1 }} van {{ presentation.students.length }}</span>
          </span>
        </button>
      </div>
      <span class="cursor-default inline-flex rounded-full border border-white/25 bg-white/10 px-3 py-1.5 text-white/75 shadow-lg backdrop-blur">
        {{ counter }}
      </span>
    </div>
    <span v-if="peekVisible && nextStudent" class="fixed bottom-4 right-20 text-white/70">
      Volgende: {{ nextStudent.fullName }}
    </span>

    <section
      v-if="testPatternVisible"
      class="fixed inset-0 z-20 flex items-center justify-center overflow-hidden bg-slate-950 text-white"
    >
      <div class="absolute inset-x-0 top-0 grid h-1/3 grid-cols-7">
        <div class="bg-white"></div>
        <div class="bg-yellow-300"></div>
        <div class="bg-cyan-300"></div>
        <div class="bg-green-400"></div>
        <div class="bg-fuchsia-500"></div>
        <div class="bg-red-500"></div>
        <div class="bg-blue-600"></div>
      </div>
      <div class="absolute inset-x-0 bottom-0 grid h-1/4 grid-cols-7 opacity-85">
        <div class="bg-blue-950"></div>
        <div class="bg-white"></div>
        <div class="bg-purple-900"></div>
        <div class="bg-slate-950"></div>
        <div class="bg-cyan-900"></div>
        <div class="bg-slate-200"></div>
        <div class="bg-slate-950"></div>
      </div>
      <div class="absolute h-[62vmin] w-[62vmin] rounded-full border-[3vmin] border-white/70"></div>
      <div class="absolute h-[44vmin] w-[44vmin] rounded-full border border-white/30"></div>
      <div class="relative mx-6 max-w-4xl rounded-3xl border border-white/20 bg-slate-950/70 px-10 py-8 text-center shadow-2xl backdrop-blur">
        <p class="text-sm font-bold uppercase tracking-[0.5em] text-gold">DiploPresent</p>
        <h1 class="mt-5 text-5xl font-black tracking-tight md:text-7xl">We zijn zo bij u terug</h1>
        <p class="mt-5 text-2xl text-white/80 md:text-3xl">even geduld a.u.b.</p>
      </div>
    </section>

    <button
      class="button-primary fixed bottom-5 right-5 z-30 rounded-full p-3 transition-opacity duration-300"
      :class="controlsVisible ? 'opacity-100' : 'pointer-events-none opacity-0'"
      :aria-label="fullscreen ? 'Volledig scherm verlaten' : 'Volledig scherm openen'"
      tabindex="0"
      @focus="revealControls"
      @click="toggleFullscreen"
    >
      <Minimize v-if="fullscreen" :size="22" />
      <Maximize v-else :size="22" />
    </button>
  </main>
</template>
