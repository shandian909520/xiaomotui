import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAppStore = defineStore('app', () => {
  const sidebarOpened = ref(true)
  const theme = ref('light')

  function toggleSidebar() {
    sidebarOpened.value = !sidebarOpened.value
  }

  function setTheme(newTheme) {
    theme.value = newTheme
  }

  return {
    sidebarOpened,
    theme,
    toggleSidebar,
    setTheme
  }
})
