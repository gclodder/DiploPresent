<script setup>
import { computed } from 'vue'
import { photoUrl } from '../domain/students'

const props = defineProps({
  studentNumber: { type: String, required: true },
  photoBaseUrl: { type: String, required: true },
})

const fallback = `${import.meta.env.BASE_URL}images/no-photo.jpg`
const oldPhoto = computed(() => photoUrl(props.photoBaseUrl, props.studentNumber, 1))
const newPhoto = computed(() => photoUrl(props.photoBaseUrl, props.studentNumber, 2))
const useFallback = (event) => {
  event.target.src = fallback
}
</script>

<template>
  <div class="relative h-12 w-20">
    <img
      :src="oldPhoto"
      alt=""
      class="absolute left-0 top-1 h-10 w-10 rounded-full object-cover grayscale"
      @error="useFallback"
    />
    <img
      :src="newPhoto"
      alt=""
      class="absolute left-7 top-0 h-12 w-12 rounded-full object-cover ring-2 ring-white"
      @error="useFallback"
    />
  </div>
</template>
