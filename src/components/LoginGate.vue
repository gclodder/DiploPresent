<script setup>
import { ref } from 'vue'
import { LockKeyhole } from 'lucide-vue-next'

defineProps({
  busy: { type: Boolean, default: false },
  configured: { type: Boolean, default: true },
  error: { type: String, default: '' },
})

const emit = defineEmits(['login'])
const password = ref('')
const logo = `${import.meta.env.BASE_URL}images/DP-logo-inv.jpg`

function submit() {
  emit('login', password.value)
}
</script>

<template>
  <main class="page-shell grid min-h-screen place-items-center">
    <section class="panel w-full max-w-md text-center">
      <img :src="logo" alt="DiploPresent" class="mx-auto mb-8 w-64" />
      <LockKeyhole :size="48" class="mx-auto text-navy" />
      <h1 class="mt-4 text-2xl font-bold">Inloggen</h1>
      <p class="muted mt-2">
        Voer het DiploPresent-wachtwoord in om verder te gaan.
      </p>

      <form class="mt-6 space-y-4 text-left" @submit.prevent="submit">
        <label class="block">
          <span class="mb-1 block font-semibold">Wachtwoord</span>
          <input
            v-model="password"
            class="field"
            type="password"
            autocomplete="current-password"
            autofocus
            :disabled="busy || !configured"
          />
        </label>

        <p v-if="!configured" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">
          Login is nog niet geconfigureerd. Zet <code>DIPLOPRESENT_PASSWORD_HASH</code> in <code>.env</code>.
        </p>
        <p v-else-if="error" class="rounded-lg bg-red-100 p-3 text-sm font-semibold text-red-800">
          {{ error }}
        </p>

        <button class="button-secondary w-full" type="submit" :disabled="busy || !configured || !password">
          {{ busy ? 'Inloggen…' : 'Inloggen' }}
        </button>
      </form>
    </section>
  </main>
</template>
