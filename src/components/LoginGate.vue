<script setup>
import { computed, ref, watch } from 'vue'
import { LockKeyhole } from 'lucide-vue-next'

const props = defineProps({
  busy: { type: Boolean, default: false },
  configured: { type: Boolean, default: true },
  configuredUser: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['login'])
const password = ref('')
const role = ref('user')
const logo = `${import.meta.env.BASE_URL}images/DP-logo-inv.jpg`
const selectedRoleConfigured = computed(() => (role.value === 'admin' ? props.configured : props.configuredUser))

watch(
  () => props.configuredUser,
  (configuredUser) => {
    if (!configuredUser) {
      role.value = 'admin'
    }
  },
  { immediate: true },
)

function submit() {
  emit('login', { role: role.value, password: password.value })
}
</script>

<template>
  <main class="page-shell grid min-h-screen place-items-center">
    <section class="panel w-full max-w-md text-center">
      <img :src="logo" alt="DiploPresent" class="mx-auto mb-8 w-64" />
      <LockKeyhole :size="48" class="mx-auto text-navy" />
      <h1 class="mt-4 text-2xl font-bold">Inloggen</h1>
      <p class="muted mt-2">
        Kies je login en voer het bijbehorende wachtwoord in.
      </p>

      <form class="mt-6 space-y-4 text-left" @submit.prevent="submit">
        <fieldset class="grid grid-cols-2 overflow-hidden rounded-lg border border-slate-300">
          <label
            class="cursor-pointer px-3 py-2 text-center font-semibold transition"
            :class="role === 'user' ? 'bg-navy text-white' : 'bg-white text-ink'"
          >
            <input v-model="role" class="sr-only" type="radio" value="user" :disabled="busy || !configuredUser" />
            Gebruiker
          </label>
          <label
            class="cursor-pointer border-l border-slate-300 px-3 py-2 text-center font-semibold transition"
            :class="role === 'admin' ? 'bg-navy text-white' : 'bg-white text-ink'"
          >
            <input v-model="role" class="sr-only" type="radio" value="admin" :disabled="busy || !configured" />
            Beheer
          </label>
        </fieldset>

        <label class="block">
          <span class="mb-1 block font-semibold">Wachtwoord</span>
          <input
            v-model="password"
            class="field"
            type="password"
            autocomplete="current-password"
            autofocus
            :disabled="busy || !selectedRoleConfigured"
          />
        </label>

        <p v-if="!configured" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">
          Admin-login is nog niet geconfigureerd. Zet <code>DIPLOPRESENT_ADMIN_PASSWORD_HASH</code> in <code>.env</code>.
        </p>
        <p v-else-if="role === 'user' && !configuredUser" class="rounded-lg bg-amber-100 p-3 text-sm font-semibold text-amber-900">
          Gebruikerslogin is nog niet geconfigureerd. Log in als admin en roteer het gebruikerswachtwoord in Beheer.
        </p>
        <p v-else-if="error" class="rounded-lg bg-red-100 p-3 text-sm font-semibold text-red-800">
          {{ error }}
        </p>

        <button class="button-secondary w-full" type="submit" :disabled="busy || !selectedRoleConfigured || !password">
          {{ busy ? 'Inloggen…' : 'Inloggen' }}
        </button>
      </form>
    </section>
  </main>
</template>
