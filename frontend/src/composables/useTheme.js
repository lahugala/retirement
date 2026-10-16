import { ref, watch } from 'vue'

const storedTheme = localStorage.getItem('theme')
const isDark = ref(storedTheme === 'dark')

function applyTheme(value) {
  document.documentElement.dataset.theme = value ? 'dark' : 'light'
}

applyTheme(isDark.value)

watch(isDark, (value) => {
  const theme = value ? 'dark' : 'light'
  localStorage.setItem('theme', theme)
  applyTheme(value)
})

export function useTheme() {
  function toggleTheme() {
    isDark.value = !isDark.value
  }

  return { isDark, toggleTheme }
}
