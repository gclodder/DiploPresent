<script setup>
import { RouterLink } from 'vue-router'
import { Presentation, Settings, UsersRound } from 'lucide-vue-next'
import AppHeader from '../components/AppHeader.vue'
</script>

<template>
  <main class="page-shell grid min-h-screen place-items-center">
    <div class="content-width">
      <AppHeader />
      <section class="mx-auto grid max-w-5xl gap-6 md:grid-cols-3">
        <RouterLink
          to="/editor"
          class="group panel relative overflow-hidden transition hover:-rotate-1 hover:scale-[1.02]"
        >
          <div class="absolute inset-0 origin-bottom scale-y-0 bg-gold transition-transform duration-300 group-hover:scale-y-100 z-[-1]" />
          <UsersRound :size="64" class="mb-4 text-navy" />
          <h1 class="text-2xl font-bold">Editor</h1>
          <p class="mt-2 text-slate-600">Importeer leerlingen, filter mentoren en bepaal de presentatievolgorde.</p>
        </RouterLink>
        <RouterLink
          to="/presenter"
          class="group panel relative overflow-hidden transition hover:rotate-1 hover:scale-[1.02]"
        >
          <div class="absolute inset-0 origin-bottom scale-y-0 bg-gold transition-transform duration-300 group-hover:scale-y-100 z-[-1]" />
          <Presentation :size="64" class="mb-4 text-navy" />
          <h1 class="text-2xl font-bold">Presenter</h1>
          <p class="mt-2 text-slate-600">Kies een opgeslagen lijst en start de diplomapresentatie.</p>
        </RouterLink>
        <RouterLink
          to="/beheer"
          class="group panel relative overflow-hidden transition hover:-rotate-1 hover:scale-[1.02]"
        >
          <div class="absolute inset-0 origin-bottom scale-y-0 bg-gold transition-transform duration-300 group-hover:scale-y-100 z-[-1]" />
          <Settings :size="64" class="mb-4 text-navy" />
          <h1 class="text-2xl font-bold">Beheer</h1>
          <p class="mt-2 text-slate-600">Beheer groepsfoto’s en standaardinstellingen voor presentaties.</p>
        </RouterLink>
      </section>
    </div>
  </main>
</template>
