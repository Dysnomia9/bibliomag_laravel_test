<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineEmits<{ toggleSidebar: [] }>()
const auth = useAuthStore()
const router = useRouter()

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="h-16 shrink-0 bg-white border-b border-biblioteca-200 flex items-center justify-between px-4 sm:px-6">
    <div class="flex items-center gap-3">
      <button
        class="lg:hidden -ml-1 p-2 rounded-md text-biblioteca-600 hover:bg-biblioteca-50"
        @click="$emit('toggleSidebar')"
        aria-label="Abrir menú"
      >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <h2 class="text-base sm:text-lg font-serif font-semibold text-biblioteca-900">Inicio</h2>
    </div>

    <div class="flex items-center gap-3">
      <div class="hidden sm:block text-right leading-tight">
        <p class="text-sm font-medium text-biblioteca-900">{{ auth.staff?.nombre ?? 'Personal' }}</p>
        <p class="text-xs text-biblioteca-500 capitalize">{{ auth.staff?.rol ?? 'staff' }}</p>
      </div>
      <div class="h-9 w-9 rounded-full bg-biblioteca-200 flex items-center justify-center text-biblioteca-800 font-medium text-sm">
        {{ (auth.staff?.nombre ?? 'P').charAt(0) }}
      </div>
      <button
        @click="onLogout"
        class="text-biblioteca-500 hover:text-biblioteca-800 p-2 rounded-md hover:bg-biblioteca-50"
        aria-label="Cerrar sesión"
        title="Cerrar sesión"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </div>
  </header>
