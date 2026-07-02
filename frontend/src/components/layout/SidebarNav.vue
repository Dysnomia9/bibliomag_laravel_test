<script setup lang="ts">
import { useRoute } from 'vue-router'

defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()
const route = useRoute()

const links = [
  { name: 'dashboard', label: 'Inicio', icon: 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h4a1 1 0 001-1V10' },
  { name: 'entrada', label: 'Entradas', icon: 'M11 16l-4-4m0 0l4-4m-4 4h14M5 5v14' },
  { name: 'prestamo', label: 'Préstamos', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253' },
  { name: 'usuarios', label: 'Usuarios', icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3a4 4 0 00-3-3.87M9 12a4 4 0 100-8 4 4 0 000 8z' },
  { name: 'salas', label: 'Salas', icon: 'M4 6h16M4 12h16M4 18h7' },
  { name: 'reportes', label: 'Reportes', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
]
</script>

<template>
  <!-- Overlay móvil -->
  <div
    v-if="open"
    class="fixed inset-0 z-30 bg-biblioteca-950/40 lg:hidden"
    @click="emit('close')"
  />

  <aside
    class="fixed z-40 inset-y-0 left-0 w-64 bg-biblioteca-800 text-biblioteca-100 flex flex-col transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:z-auto"
    :class="open ? 'translate-x-0' : '-translate-x-full'"
  >
    <div class="h-16 flex items-center gap-2.5 px-5 border-b border-biblioteca-700/60 shrink-0">
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-biblioteca-50 text-biblioteca-800 font-serif font-semibold">B</div>
      <div class="leading-tight">
        <p class="font-serif font-semibold text-sm text-white">Biblioteca UMAG</p>
        <p class="text-[11px] text-biblioteca-300">Panel de administración</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
      <router-link
        v-for="link in links"
        :key="link.name"
        :to="{ name: link.name }"
        @click="emit('close')"
        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
        :class="route.name === link.name
          ? 'bg-biblioteca-700 text-white'
          : 'text-biblioteca-200 hover:bg-biblioteca-700/60 hover:text-white'"
      >
        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
        </svg>
        {{ link.label }}
      </router-link>
    </nav>

    <div class="px-3 py-4 border-t border-biblioteca-700/60 text-[11px] text-biblioteca-400">
      Universidad de Magallanes
    </div>
  </aside>
