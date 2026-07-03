<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logoUmag from '@/assets/logo-umag.png'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const generalLinks = [
  { name: 'dashboard', label: 'Inicio', icon: 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h4a1 1 0 001-1V10', shortcut: '1' },
]

const moduleLinks = [
  { name: 'entrada', label: 'Entradas', icon: 'M11 16l-4-4m0 0l4-4m-4 4h14M5 5v14', shortcut: '2' },
  { name: 'prestamo', label: 'Préstamos', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', shortcut: '3' },
  { name: 'usuarios', label: 'Usuarios', icon: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3a4 4 0 00-3-3.87M9 12a4 4 0 100-8 4 4 0 000 8z', shortcut: '4' },
  { name: 'salas', label: 'Salas', icon: 'M4 6h16M4 12h16M4 18h7', shortcut: '5' },
  { name: 'reportes', label: 'Reportes', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', shortcut: '6' },
]

const now = ref(new Date())
let intervalId: ReturnType<typeof setInterval> | undefined

onMounted(() => {
  intervalId = setInterval(() => { now.value = new Date() }, 1000)
})
onUnmounted(() => {
  if (intervalId) clearInterval(intervalId)
})

const fechaLabel = computed(() =>
  now.value.toLocaleDateString('es-CL', { weekday: 'short', day: '2-digit', month: 'short' })
)
const horaLabel = computed(() =>
  now.value.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
)

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header
    class="h-16 shrink-0 flex items-center px-4 sm:px-8 gap-4"
    style="background: linear-gradient(135deg, #2D1B69 0%, #3B28A3 30%, #4338CA 60%, #4F46E5 100%); border-bottom: 1px solid rgba(255,255,255,0.1);"
  >
    <!-- Logo -->
    <div class="flex items-center gap-2.5 shrink-0">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 overflow-hidden">
        <img :src="logoUmag" alt="UMAG" class="h-full w-full object-cover" />
      </div>
      <div class="leading-tight hidden sm:block">
        <p class="text-[13px] font-semibold text-slate-100">Biblioteca UMAG</p>
        <p class="text-[10px] uppercase tracking-wider text-slate-400">Panel de administración</p>
      </div>
    </div>

    <div class="h-6 w-px bg-white/10 shrink-0 hidden md:block" />

    <!-- Navegación -->
    <nav class="flex-1 min-w-0 overflow-x-auto">
      <div class="flex items-center gap-1.5">
        <router-link
          v-for="link in generalLinks"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex items-center gap-1.5 h-9 px-3 rounded-lg text-[13px] font-medium shrink-0 transition-colors"
          :class="route.name === link.name
            ? 'bg-[#b08d57] text-[#1a2430]'
            : 'text-slate-200 hover:bg-white/5'"
          :style="route.name === link.name ? 'box-shadow: inset 0 1px 0 rgba(255,255,255,0.28);' : ''"
        >
          <svg class="h-3.5 w-3.5 shrink-0" :class="route.name === link.name ? 'text-[#1a2430]' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
          </svg>
          {{ link.label }}
          <kbd
            class="text-[10px] leading-none rounded px-1 py-0.5 font-mono"
            :class="route.name === link.name ? 'bg-[#1a2430]/15 text-[#1a2430]' : 'bg-white/5 text-slate-500'"
          >{{ link.shortcut }}</kbd>
        </router-link>

        <div class="h-6 w-px bg-white/10 shrink-0 mx-1" />

        <router-link
          v-for="link in moduleLinks"
          :key="link.name"
          :to="{ name: link.name }"
          class="flex items-center gap-1.5 h-9 px-3 rounded-lg text-[13px] font-medium shrink-0 transition-colors"
          :class="route.name === link.name
            ? 'bg-[#b08d57] text-[#1a2430]'
            : 'text-slate-200 hover:bg-white/5'"
          :style="route.name === link.name ? 'box-shadow: inset 0 1px 0 rgba(255,255,255,0.28);' : ''"
        >
          <svg class="h-3.5 w-3.5 shrink-0" :class="route.name === link.name ? 'text-[#1a2430]' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
          </svg>
          {{ link.label }}
          <kbd
            class="text-[10px] leading-none rounded px-1 py-0.5 font-mono"
            :class="route.name === link.name ? 'bg-[#1a2430]/15 text-[#1a2430]' : 'bg-white/5 text-slate-500'"
          >{{ link.shortcut }}</kbd>
        </router-link>
      </div>
    </nav>

    <div class="h-6 w-px bg-white/10 shrink-0 hidden lg:block" />

    <!-- Reloj -->
    <div class="hidden lg:flex items-center gap-1.5 text-[13px] text-slate-300 shrink-0">
      <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="capitalize">{{ fechaLabel }}</span>
      <span class="tabular-nums">{{ horaLabel }}</span>
    </div>

    <div class="h-6 w-px bg-white/10 shrink-0" />

    <!-- Usuario -->
    <div class="flex items-center gap-3 shrink-0">
      <div class="hidden sm:block text-right leading-tight">
        <p class="text-[13px] font-medium text-slate-100">{{ auth.staff?.nombre ?? 'Personal' }}</p>
        <p class="text-[11px] text-slate-400 capitalize">{{ auth.staff?.rol ?? 'staff' }}</p>
      </div>
      <div class="h-9 w-9 rounded-full bg-white/10 flex items-center justify-center text-slate-100 font-medium text-sm">
        {{ (auth.staff?.nombre ?? 'P').charAt(0) }}
      </div>
      <button
        @click="onLogout"
        class="text-slate-400 hover:text-slate-100 p-2 rounded-md hover:bg-white/5"
        aria-label="Cerrar sesión"
        title="Cerrar sesión"
      >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      </button>
    </div>
  </header>
</template>
