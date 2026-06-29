<script setup>
import { onMounted, provide, ref } from 'vue'
import { RouterView } from 'vue-router'
import AppToast from './components/AppToast.vue'
import LoginGate from './components/LoginGate.vue'
import { api } from './api/client'
import { useUiStore } from './stores/ui'

const ui = useUiStore()
const loading = ref(true)
const loginBusy = ref(false)
const authenticated = ref(false)
const configured = ref(true)
const loginError = ref('')

async function refreshAuth() {
  loading.value = true
  try {
    const auth = await api.getAuth()
    authenticated.value = auth.authenticated
    configured.value = auth.configured
  } catch (error) {
    authenticated.value = false
    loginError.value = error.message
  } finally {
    loading.value = false
  }
}

async function login(password) {
  loginBusy.value = true
  loginError.value = ''
  try {
    const auth = await api.login(password)
    authenticated.value = auth.authenticated
    configured.value = auth.configured
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
    ui.notify('Uitgelogd.')
  } catch (error) {
    ui.notify(error.message)
  }
}

onMounted(refreshAuth)
provide('logout', logout)
</script>

<template>
  <main v-if="loading" class="page-shell grid min-h-screen place-items-center">
    <p class="text-white/70">DiploPresent laden…</p>
  </main>
  <LoginGate
    v-else-if="!authenticated"
    :busy="loginBusy"
    :configured="configured"
    :error="loginError"
    @login="login"
  />
  <RouterView v-else />
  <AppToast />
</template>
