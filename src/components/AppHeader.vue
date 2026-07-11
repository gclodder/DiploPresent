<script setup>
import { inject } from 'vue'
import { RouterLink } from 'vue-router'

defineProps({
  compact: { type: Boolean, default: false },
})

const logo = `${import.meta.env.BASE_URL}images/DP-logo.png`
const logout = inject('logout', null)
const auth = inject('auth', null)
</script>

<template>
  <header class="content-width flex items-center gap-6 pb-8" :class="compact ? 'justify-between' : 'justify-center'">
    <RouterLink to="/" aria-label="Naar startpagina">
      <img
        :src="logo"
        alt="DiploPresent"
        :class="compact ? 'w-44' : 'w-64'"
        class="transition hover:rotate-2 hover:scale-105"
      />
    </RouterLink>
    <nav v-if="compact" class="flex gap-3">
      <RouterLink class="button-secondary" to="/editor">Editor</RouterLink>
      <RouterLink class="button-secondary" to="/presenter">Presenter</RouterLink>
      <RouterLink v-if="auth?.isAdmin.value" class="button-secondary" to="/beheer">Beheer</RouterLink>
      <button v-if="logout" class="button-secondary" type="button" @click="logout">Uitloggen</button>
    </nav>
  </header>
</template>
