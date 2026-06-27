import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', {
  state: () => ({
    toast: null,
    timer: null,
  }),
  actions: {
    notify(message, duration = 3500) {
      this.clearToast()
      this.toast = { message }
      this.timer = window.setTimeout(() => this.clearToast(), duration)
    },
    clearToast() {
      if (this.timer) window.clearTimeout(this.timer)
      this.timer = null
      this.toast = null
    },
  },
})
