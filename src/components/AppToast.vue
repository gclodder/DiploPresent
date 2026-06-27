<script setup>
import { storeToRefs } from 'pinia'
import { X } from 'lucide-vue-next'
import { useUiStore } from '../stores/ui'

const ui = useUiStore()
const { toast } = storeToRefs(ui)
</script>

<template>
  <Transition
    enter-active-class="transition duration-200"
    enter-from-class="-translate-y-3 opacity-0"
    leave-active-class="transition duration-200"
    leave-to-class="-translate-y-3 opacity-0"
  >
    <div
      v-if="toast"
      class="fixed left-1/2 top-5 z-50 flex min-w-72 -translate-x-1/2 items-start gap-3 rounded-xl border border-white/20 bg-ink px-4 py-3 text-white shadow-2xl"
      role="status"
    >
      <p class="flex-1">{{ toast.message }}</p>
      <button aria-label="Sluiten" @click="ui.clearToast"><X :size="18" /></button>
    </div>
  </Transition>
</template>
