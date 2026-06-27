import { defineStore } from 'pinia'

const STORAGE_KEY = 'diplopresent.presentation'

export const usePresentationStore = defineStore('presentation', {
  state: () => ({
    listName: '',
    students: [],
    title: '',
    department: 'havo',
    photoBaseUrl: 'storage/photos',
  }),
  actions: {
    restore() {
      const raw = sessionStorage.getItem(STORAGE_KEY)
      if (!raw) return
      try {
        Object.assign(this, JSON.parse(raw))
      } catch {
        sessionStorage.removeItem(STORAGE_KEY)
      }
    },
    persist() {
      sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          listName: this.listName,
          students: this.students,
          title: this.title,
          department: this.department,
          photoBaseUrl: this.photoBaseUrl,
        }),
      )
    },
  },
})
