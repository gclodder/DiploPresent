<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { Heart, Presentation, Settings, UsersRound } from 'lucide-vue-next'
import AppHeader from '../components/AppHeader.vue'

const creditsOpen = ref(false)
const appVersion = __APP_VERSION__
</script>

<template>
  <main class="page-shell grid min-h-screen place-items-center">
    <div class="content-width">
      <AppHeader />
      <section class="mx-auto grid max-w-5xl gap-6 md:grid-cols-3">
        <RouterLink
          to="/editor"
          class="home-card home-card-left panel"
        >
          <UsersRound :size="64" class="mb-4 text-navy" />
          <h1 class="text-2xl font-bold">Editor</h1>
          <p class="mt-2 text-slate-600">Importeer leerlingen, filter mentoren en bepaal de presentatievolgorde.</p>
        </RouterLink>
        <RouterLink
          to="/presenter"
          class="home-card home-card-right panel"
        >
          <Presentation :size="64" class="mb-4 text-navy" />
          <h1 class="text-2xl font-bold">Presenter</h1>
          <p class="mt-2 text-slate-600">Kies een opgeslagen lijst en start de diplomapresentatie.</p>
        </RouterLink>
        <RouterLink
          to="/beheer"
          class="home-card home-card-left panel"
        >
          <Settings :size="64" class="mb-4 text-navy" />
          <h1 class="text-2xl font-bold">Beheer</h1>
          <p class="mt-2 text-slate-600">Beheer groepsfoto’s en standaardinstellingen voor presentaties.</p>
        </RouterLink>
      </section>

      <footer class="mt-10 text-center">
        <button
          type="button"
          class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white/75 transition hover:bg-white/10 hover:text-white"
          @click="creditsOpen = true"
        >
          Met <Heart :size="16" class="fill-gold text-gold" /> gemaakt
        </button>
      </footer>
    </div>

    <Teleport to="body">
      <div
        v-if="creditsOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm"
        @click.self="creditsOpen = false"
      >
        <section
          class="max-w-xl rounded-3xl border border-white/20 bg-white p-8 text-center text-ink shadow-2xl"
          @click.stop
        >
          <Heart :size="42" class="mx-auto fill-gold text-gold" />
          <h3 class="mt-5 text-2xl font-black tracking-tight text-gold drop-shadow-sm">
            DiploPresent <span class="text-gold-dark">v{{ appVersion }}</span>
          </h3>
          <p class="mt-4 text-lg leading-relaxed">
            Deze app is gemaakt door Gerben Lodder, maar had niet kunnen bestaan zonder de input, tests en oneindig geduld van Eric Craane!
          </p>
        </section>
      </div>
    </Teleport>
  </main>
</template>
