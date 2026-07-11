<script setup>
import { computed, onMounted, provide, ref } from 'vue'
import { RouterView } from 'vue-router'
import AppToast from './components/AppToast.vue'
import LoginGate from './components/LoginGate.vue'
import { api } from './api/client'
import { useUiStore } from './stores/ui'

const ui = useUiStore()
const loading = ref(true)
const loginBusy = ref(false)
const authenticated = ref(false)
const role = ref('')
const configured = ref(true)
const configuredUser = ref(false)
const loginError = ref('')
const isAdmin = computed(() => role.value === 'admin')

function applyAuth(auth) {
  authenticated.value = auth.authenticated
  role.value = auth.role || ''
  configured.value = auth.configured
  configuredUser.value = auth.configuredUser
}

async function refreshAuth() {
  loading.value = true
  try {
    const auth = await api.getAuth()
    applyAuth(auth)
  } catch (error) {
    authenticated.value = false
    role.value = ''
    loginError.value = error.message
  } finally {
    loading.value = false
  }
}

async function login({ role: loginRole, password }) {
  loginBusy.value = true
  loginError.value = ''
  try {
    const auth = await api.login(loginRole, password)
    applyAuth(auth)
  } catch (error) {
    loginError.value = error.message
  } finally {
    loginBusy.value = false
  }
}

async function logout() {
  try {
    await api.logout()
    authenticated.value = false
    role.value = ''
    ui.notify('Uitgelogd.')
  } catch (error) {
    ui.notify(error.message)
  }
}

onMounted(refreshAuth)
provide('logout', logout)
provide('auth', { role, isAdmin, refreshAuth })
</script>

<template>
  <main v-if="loading" class="page-shell grid min-h-screen place-items-center">
    <p class="text-white/70">DiploPresent laden…</p>
  </main>
  <LoginGate
    v-else-if="!authenticated"
    :busy="loginBusy"
    :configured="configured"
    :configured-user="configuredUser"
    :error="loginError"
    @login="login"
  />
  <RouterView v-else />
  <AppToast />
</template>
